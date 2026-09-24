<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMentorRequest;
use App\Http\Requests\Admin\UpdateMentorRequest;
use App\Models\Cohort;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\VersionHistory;
use App\Support\ChangeLog;
use App\Support\HistoryFields;
use App\Traits\CompressesImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MentorController extends Controller
{
    use CompressesImages;

    public function index(): View
    {
        // Mentors themselves aren't cohort-scoped (one mentor can serve
        // startups across every cohort), so the app-wide selected cohort
        // (see ResolveSelectedCohort) narrows down which of their cases show
        // up here — the Active Cases / Completed counts and lists only ever
        // count startups in that cohort — rather than filtering out mentors.
        $cohortId = session('selected_cohort_id');

        // Resolved to the Cohort's `number`, not filtered on cohort_id
        // directly below: cohort_number is the field that's actually
        // reliably populated on every startup (see StartupProfileController::
        // index()'s same fix) — filtering on cohort_id alone left this list
        // empty for any startup whose cohort_id never got backfilled/synced
        // to match its already-correct, already-displayed cohort_number.
        $cohortNumber = $cohortId ? Cohort::find($cohortId)?->number : null;

        // Eager-loaded (with their startup) so the Active Cases / Completed
        // stat on each mentor card can list the actual startups behind those
        // counts without an extra query per click — see
        // Mentor::getActiveCasesCountAttribute()/getCompletedCasesCountAttribute(),
        // which read from this loaded collection instead of re-querying.
        $mentors = Mentor::with(['roadblocks' => function ($query) use ($cohortNumber) {
            $query->whereIn('status', array_merge(Roadblock::ACTIVE_STATUSES, ['Resolved', 'Failed']))
                ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
                ->with('startup')
                ->latest();
        }])->latest()->get();

        // "Others" expertise suggestions — pulled from every mentor's past
        // free-text entries (not just whoever's being added/edited right
        // now), same idea as Roadblock's problem_category_other suggestions.
        $otherSpecializationSuggestions = Mentor::where('specialization', 'Others')
            ->whereNotNull('specialization_other')
            ->where('specialization_other', '!=', '')
            ->distinct()
            ->orderBy('specialization_other')
            ->pluck('specialization_other')
            ->values();

        return view('admin.mentors.index', [
            'mentors' => $mentors,
            'otherSpecializationSuggestions' => $otherSpecializationSuggestions,
            // One shared, page-wide Edit History feed of every Add/Edit/
            // Delete Mentor action together — a mentor isn't tied to one
            // startup, so it follows the cohort selected on this page
            // instead: each entry is filed under whichever cohort was
            // selected when the change was made (see VersionHistory::record()),
            // and picking another cohort here shows only that cohort's
            // entries. "All Cohorts" lists every one, labelled by cohort.
            'mentorVersionHistory' => VersionHistory::where('context', 'Mentor Profile')
                ->forSelectedCohort()
                ->with('user')
                ->newestFirst()
                ->get(),
            'selectedCohortId' => $cohortId ? (int) $cohortId : null,
            'filterCohorts' => Cohort::orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                ->orderBy('number')
                ->get(),
        ]);
    }

    public function store(StoreMentorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['full_name'] = trim("{$data['honorific']} {$data['first_name']} {$data['last_name']}");

        if (! empty($data['contact_email'])) {
            $data['contact_email'] = strtolower($data['contact_email']);
        }

        if ($request->hasFile('mentor_photo')) {
            try {
                $data['mentor_photo_path'] = $this->compressAndStoreImage($request->file('mentor_photo'), 'mentors');
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'mentor_photo' => "That photo couldn't be processed ({$e->getMessage()}). Please try a different file.",
                ]);
            }
        }

        $mentor = Mentor::create($data);

        VersionHistory::record(
            null,
            'Mentor Profile',
            'create_mentor',
            $mentor->display_name,
            changes: ChangeLog::initial($mentor, HistoryFields::mentor()),
        );

        return redirect()->route('admin.mentors.index')->with('status', 'Mentor added successfully.');
    }

    public function update(UpdateMentorRequest $request, Mentor $mentor): RedirectResponse
    {
        $data = $request->validated();
        $data['full_name'] = trim("{$data['honorific']} {$data['first_name']} {$data['last_name']}");

        if (! empty($data['contact_email'])) {
            $data['contact_email'] = strtolower($data['contact_email']);
        }

        if ($request->hasFile('mentor_photo')) {
            // Compress the new photo *before* touching the old one — if
            // processing fails, the mentor keeps their existing photo
            // instead of ending up with none at all.
            try {
                $newPhotoPath = $this->compressAndStoreImage($request->file('mentor_photo'), 'mentors');
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'mentor_photo' => "That photo couldn't be processed ({$e->getMessage()}). Please try a different file.",
                ]);
            }

            if ($mentor->mentor_photo_path) {
                Storage::disk('public')->delete($mentor->mentor_photo_path);
            }
            $data['mentor_photo_path'] = $newPhotoPath;
        }

        // Which fields this save really changed (see ChangeLog) — a save that
        // changes nothing isn't logged at all.
        $changes = ChangeLog::track($mentor, HistoryFields::mentor(), fn () => $mentor->update($data));

        VersionHistory::recordChanges(null, 'Mentor Profile', 'update_mentor', $changes, $mentor->display_name);

        return redirect()->route('admin.mentors.index')->with('status', 'Mentor updated successfully.');
    }

    public function destroy(Mentor $mentor): RedirectResponse
    {
        // History stays when a mentor profile is deleted. The mentor_id FK
        // is ON DELETE SET NULL, so the mentor's name is captured onto every
        // roadblock whose session already happened (Pending Review, Resolved,
        // Failed, Deleted by Admin) BEFORE the delete — Roadblock Management,
        // the founder's Archive, etc. then show it as "Name (Deleted)".
        //
        // Sweep first, so a Scheduled session whose time has already passed
        // is treated as Pending Review (it happened) rather than reset.
        Roadblock::promoteEndedMeetingsToPendingReview();

        $mentor->roadblocks()
            ->whereIn('status', ['Pending Review', 'Resolved', 'Failed', 'Deleted by Admin'])
            ->update(['assignee_name_snapshot' => $mentor->display_name]);

        // Only a session that hasn't happened yet goes back to Pending, so
        // the admin can assign someone else — there's no history for it yet.
        $mentor->roadblocks()
            ->where('status', 'Scheduled')
            ->get()
            ->each(fn (Roadblock $roadblock) => $roadblock->update(Roadblock::pendingResetAttributes()));

        if ($mentor->mentor_photo_path) {
            Storage::disk('public')->delete($mentor->mentor_photo_path);
        }

        $mentorName = $mentor->display_name;

        $mentor->delete();

        VersionHistory::record(null, 'Mentor Profile', 'delete_mentor', $mentorName);

        return redirect()->route('admin.mentors.index')->with('status', 'Mentor removed.');
    }
}