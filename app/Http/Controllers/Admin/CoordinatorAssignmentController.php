<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCoordinatorRequest;
use App\Models\Coordinator;
use App\Models\Startup;
use App\Models\VersionHistory;
use App\Support\ChangeLog;
use App\Notifications\CoordinatorAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoordinatorAssignmentController extends Controller
{
    public function store(AssignCoordinatorRequest $request, Startup $startup): RedirectResponse
    {
        // Who (if anyone) held the slot before this request — read before the
        // transaction below marks it Completed. Decides between an
        // "assigned" and a "changed" card, and whether anything changed at all.
        $previousCoordinatorId = $startup->activeCoordinatorAssignment?->coordinator_id;
        // By name, for the Edit History line ("Portfolio Coordinator: Ms. Ana
        // Reyes → Ms. Ana Cruz") — never the ids.
        $previousCoordinatorName = $startup->activeCoordinatorAssignment?->coordinator?->name;
        $coordinator = null;

        DB::transaction(function () use ($request, $startup, &$coordinator, $previousCoordinatorName) {
            $startup->coordinatorAssignments()->where('assignment_status', 'Active')
                ->update(['assignment_status' => 'Completed']);

            $startup->coordinatorAssignments()->create([
                'coordinator_id' => $request->validated('coordinator_id'),
                'assigned_date' => now(),
                'assignment_status' => 'Active',
            ]);

            $coordinator = Coordinator::findOrFail($request->validated('coordinator_id'));
            $coordinator->increment('assigned_startups_count');

            // Re-picking the coordinator a startup already has changes nothing,
            // so it isn't logged (same rule as the founder notification below).
            VersionHistory::recordChanges(
                $startup,
                'Startup Profile',
                'assign_coordinator',
                ChangeLog::field('Portfolio Coordinator', $previousCoordinatorName, $coordinator->name),
                "{$coordinator->name} → {$startup->company_name}",
            );

            // The Information Sheet has its own "Portfolio Manager" field
            // (see admin/information-sheets/show.blade.php's
            // $selectField('portfolio_manager', ...)) — a separate column,
            // not read from coordinatorAssignments. Without this, assigning
            // someone here left that field blank until an admin separately
            // reopened and resaved the sheet, even though the assignment
            // this button makes IS the actual answer to "who is the
            // portfolio manager". Only touches an already-existing sheet —
            // a startup can be assigned a coordinator before it has even
            // started one.
            $startup->informationSheet?->update(['portfolio_manager' => $coordinator->name]);
        });

        // Outside the transaction so a founder is never told about an
        // assignment that ended up rolled back.
        $this->notifyFounder($startup, $coordinator, $previousCoordinatorId);

        // The Startup Profile card grid's 3-dot "Edit Coordinator" reuses
        // this same component/route (see coordinator-assign-modal.blade.php)
        // but wants to land back on that filtered/paginated list, not always
        // on the show page. Restricted to a same-origin relative path —
        // this is user-submitted input, so it must never redirect off-app.
        $returnUrl = $request->input('return_url');
        $redirectTo = ($returnUrl && Str::startsWith($returnUrl, url('/')))
            ? $returnUrl
            : route('admin.startups.show', $startup);

        return redirect($redirectTo)
            ->with('status', 'Portfolio Coordinator assigned successfully.');
    }

    /**
     * Tells the founder who their Portfolio Coordinator is. Re-picking the
     * coordinator they already have changes nothing for them, so it stays
     * quiet; a genuine change while the earlier card is still unread updates
     * that card rather than stacking a second one.
     */
    protected function notifyFounder(Startup $startup, Coordinator $coordinator, int|string|null $previousCoordinatorId): void
    {
        $user = $startup->user;

        if (! $user || (int) $previousCoordinatorId === (int) $coordinator->coordinator_id) {
            return;
        }

        $notification = new CoordinatorAssigned($coordinator, reassigned: $previousCoordinatorId !== null);

        $existing = $user->unreadNotifications()
            ->where('type', CoordinatorAssigned::class)
            ->first();

        if ($existing) {
            $existing->forceFill(['data' => $notification->toDatabase($user)])->save();
        } else {
            $user->notify($notification);
        }
    }
}