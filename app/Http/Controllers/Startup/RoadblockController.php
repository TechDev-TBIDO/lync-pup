<?php

namespace App\Http\Controllers\Startup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Startup\StoreRoadblockRequest;
use App\Models\AssessmentDocument;
use App\Models\Roadblock;
use App\Traits\CompressesImages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoadblockController extends Controller
{
    use CompressesImages;

    public function index()
    {
        Roadblock::promoteEndedMeetingsToPendingReview();

        // Red dots: captured BEFORE this visit is stamped as seen, so the page
        // can still point at what's new; the stamp (which also clears the
        // sidebar dot) happens right before the view is returned.
        $user = Auth::user();
        $visitedAt = now();
        $seenAt = $user->founderSeenSince('founder_submissions', 'startup.submissions.index');

        $startup = $user->startup;

        $roadblocks = Roadblock::with('files')
            ->where('startup_id', $startup->startup_id)
            ->latest()
            ->get();

        // "Others" category suggestions — pulled from every startup's past submissions
        // (not just this one) so the free-text field can predict what other startups
        // have already typed for the same kind of roadblock.
        $otherCategorySuggestions = Roadblock::where('problem_category', 'Others')
            ->whereNotNull('problem_category_other')
            ->where('problem_category_other', '!=', '')
            ->distinct()
            ->orderBy('problem_category_other')
            ->pluck('problem_category_other')
            ->values();

        // Weekly Update tab — reads the admin's Document 7 ("Weekly Check-ins")
        // rows for this startup rather than any dedicated founder-facing model;
        // this is the same JSON the admin edits under Active-Assessment.
        $doc7 = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->where('document_number', 7)
            ->first();

        $weeklyUpdates = collect($doc7?->data['check_ins'] ?? [])
            // Document 7 always seeds 10 blank rows for the admin to fill in —
            // only rows the admin actually typed something into should ever
            // reach the founder.
            ->filter(fn ($row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''))
            ->sortByDesc(fn ($row) => $row['dates'] ?? '')
            ->values();

        // Archive tab: roadblocks the admin acted on since the last visit
        // (scheduled, resolved, failed, removed...). The founder's own fresh
        // submission (still Pending) isn't news to them, so it's left out.
        $newRoadblockIds = $roadblocks
            ->filter(fn (Roadblock $r) => $r->status !== 'Pending' && $r->updated_at?->gt($seenAt))
            ->pluck('roadblock_id')
            ->all();

        // Update tab: Document 7 rows carry no timestamps of their own, so each
        // row's content fingerprint is remembered per visit; a row whose
        // fingerprint wasn't there last time is new (or was edited).
        $fingerprint = fn ($row) => md5(json_encode($row));
        $seenRows = ($user->module_seen_at ?? [])['founder_weekly_rows'] ?? null;
        $newUpdateKeys = $weeklyUpdates
            ->map($fingerprint)
            ->filter(fn ($fp) => is_array($seenRows)
                ? ! in_array($fp, $seenRows, true)
                : ($doc7?->updated_at?->gt($seenAt) ?? false))
            ->values()
            ->all();

        $user->markFounderModuleVisited('founder_submissions', 'startup.submissions.index', $visitedAt);
        $user->forceFill(['module_seen_at' => array_merge($user->module_seen_at ?? [], [
            'founder_weekly_rows' => $weeklyUpdates->map($fingerprint)->values()->all(),
        ])])->save();

        return view('startup.roadblocks.index', [
            'newRoadblockIds' => $newRoadblockIds,
            'newUpdateKeys' => $newUpdateKeys,
            'weeklyFingerprint' => $fingerprint,
            'roadblocks' => $roadblocks,
            'otherCategorySuggestions' => $otherCategorySuggestions,
            'weeklyUpdates' => $weeklyUpdates,
        ]);
    }

    public function store(StoreRoadblockRequest $request)
    {
        $startup = Auth::user()->startup;

        // Anything already flagged as unsupported/too-large/half-uploaded by
        // StoreRoadblockRequest before we even got here.
        $skipped = $request->skippedFiles;

        // Wrapped in a transaction so the roadblock row itself is all-or-
        // nothing, but a single file that fails to process (an unreadable
        // image, say) no longer takes the whole submission down with it —
        // it's skipped (and reported back to the founder) while every other
        // file, and the roadblock itself, still goes through.
        $roadblock = null;

        DB::transaction(function () use ($request, $startup, &$skipped, &$roadblock) {
            $roadblock = Roadblock::create([
                'startup_id' => $startup->startup_id,
                'problem_category' => $request->validated('problem_category'),
                'problem_category_other' => $request->validated('problem_category_other'),
                'description' => $request->validated('description'),
                'status' => 'Pending',
            ]);

            foreach ($request->file('supporting_files', []) as $file) {
                $isImage = str_starts_with($file->getMimeType(), 'image/');

                try {
                    $path = $isImage
                        ? $this->compressAndStoreImage($file, "roadblocks/{$roadblock->roadblock_id}")
                        : $file->store("roadblocks/{$roadblock->roadblock_id}", 'public');
                } catch (\RuntimeException $e) {
                    $skipped[] = "\"{$file->getClientOriginalName()}\" couldn't be processed ({$e->getMessage()}).";

                    continue;
                }

                $roadblock->files()->create([
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'is_image' => $isImage,
                ]);
            }
        });

        // RedirectResponse has no when() (that's Conditionable — not used by
        // this class), which is what actually threw the BadMethodCallException
        // here despite the roadblock itself having already saved successfully.
        $redirect = redirect()
            ->route('startup.submissions.index', ['tab' => 'roadblock'])
            ->with('roadblock_submitted', true);

        if (count($skipped) > 0) {
            $redirect->with('roadblock_skipped_files', $skipped);
        }

        return $redirect;
    }
}
