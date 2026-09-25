<?php

namespace App\Console\Commands;

use App\Mail\RejectedFounderAutoDeleted;
use App\Models\Startup;
use App\Models\VersionHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Auto-deletes any startup whose Information Sheet has sat 'Rejected' for
 * 10+ days with no resubmission — the Rejected tab's countdown (see
 * Startup::rejectionDeadline()) running out unattended. A resubmission
 * flips approval_status back to 'Pending' on its own (see Startup\
 * InformationSheetController::update()), which takes a startup out of
 * this query without any extra bookkeeping here — only genuinely
 * still-Rejected rows are ever touched.
 *
 * Dry-run by default (mirrors founders:clean-orphans) so an admin can run
 * this by hand and see what it *would* remove; the actual daily automatic
 * removal is registered with --force in bootstrap/app.php's schedule.
 */
class PurgeExpiredRejections extends Command
{
    protected $signature = 'founders:purge-expired-rejections {--force : Actually delete the expired rejections instead of just listing them}';

    protected $description = "Find (and optionally delete) startups whose Information Sheet rejection window (10 days) has expired without a resubmission";

    public function handle(): int
    {
        $expired = Startup::with(['user', 'informationSheet'])
            ->whereHas('informationSheet', fn ($q) => $q
                ->where('approval_status', 'Rejected')
                ->whereNotNull('rejected_at')
                ->where('rejected_at', '<=', now()->subDays(10)))
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired rejections found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Startup ID', 'Company', 'Rejected At', 'Deadline'],
            $expired->map(fn (Startup $s) => [
                $s->startup_id,
                $s->company_name,
                $s->informationSheet->rejected_at->format('Y-m-d'),
                $s->informationSheet->rejected_at->copy()->addDays(10)->format('Y-m-d'),
            ])
        );

        if (! $this->option('force')) {
            $this->warn(count($expired).' expired rejection(s) found above. Re-run with --force to delete them.');

            return self::SUCCESS;
        }

        foreach ($expired as $startup) {
            $user = $startup->user;
            $founderName = $user?->name ?? 'Founder';
            $companyName = $startup->company_name;
            $rejectedAt = $startup->informationSheet->rejected_at;

            if ($user?->email) {
                Mail::to($user->email)->send(new RejectedFounderAutoDeleted(
                    $founderName,
                    $companyName,
                    $rejectedAt->format('F j, Y'),
                    $rejectedAt->copy()->addDays(10)->format('F j, Y'),
                ));
            }

            if ($startup->startup_photo_path) {
                Storage::disk('public')->delete($startup->startup_photo_path);
            }

            // No auth()->user() here — this runs unattended off the
            // schedule, not from a request — so actorLabel is passed
            // explicitly rather than leaving the entry looking like some
            // admin's action (or, worse, a since-deleted admin's "Deleted
            // User"). subject_label spells out *why*, not just what, since
            // that's the whole point of logging this: an admin scanning the
            // Rejected tab's history later shouldn't have to wonder whether
            // this startup was removed by a person or by the deadline.
            VersionHistory::record(
                $startup,
                'Information Sheet',
                'delete_startup',
                "{$companyName} (auto-removed — no resubmission within 10 days)",
                actorLabel: 'System',
            );

            // Information Sheet, evaluation schedules, etc. cascade-delete
            // at the database level (see each table's migration).
            $startup->delete();
            $user?->delete();
        }

        $this->info(count($expired).' expired rejection(s) auto-deleted.');

        return self::SUCCESS;
    }
}
