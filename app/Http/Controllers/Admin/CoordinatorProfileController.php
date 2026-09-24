<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCoordinatorRequest;
use App\Http\Requests\Admin\UpdateCoordinatorRequest;
use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\Roadblock;
use App\Models\VersionHistory;
use App\Support\ChangeLog;
use App\Support\HistoryFields;
use App\Traits\CompressesImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CoordinatorProfileController extends Controller
{
    use CompressesImages;

    public function index(): View
    {
        // Coordinators themselves aren't cohort-scoped (one coordinator can
        // serve startups across every cohort), so the app-wide selected
        // cohort (see ResolveSelectedCohort) narrows down which of their
        // assigned startups show up here, rather than filtering out
        // coordinators.
        $cohortId = session('selected_cohort_id');

        // Resolved to the Cohort's `number`, not filtered on cohort_id
        // directly below: cohort_number is the field that's actually
        // reliably populated on every startup (see StartupProfileController::
        // index()'s same fix) — filtering on cohort_id alone left this list
        // empty for any startup whose cohort_id never got backfilled/synced
        // to match its already-correct, already-displayed cohort_number.
        $cohortNumber = $cohortId ? Cohort::find($cohortId)?->number : null;

        // Eager-loaded (with their startup) so the "X Startup" stat on each
        // coordinator card can list the actual startups behind that count
        // without an extra query per click — see
        // Coordinator::getActiveStartupsCountAttribute(), which reads from
        // this loaded collection instead of the stale assigned_startups_count
        // column (only ever incremented, never decremented — see
        // CoordinatorAssignmentController::store()).
        $coordinators = Coordinator::with(['assignments' => function ($query) use ($cohortNumber) {
            $query->where('assignment_status', 'Active')
                ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
                ->with('startup')
                ->latest();
        }])->latest()->get();

        return view('admin.coordinators.index', [
            'coordinators' => $coordinators,
            // One shared, page-wide Edit History feed of every Add/Edit/
            // Delete Coordinator action together — same reasoning as
            // MentorController::index()'s mentorVersionHistory, including
            // following the cohort selected on this page.
            'coordinatorVersionHistory' => VersionHistory::where('context', 'Coordinator Profile')
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

    public function store(StoreCoordinatorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['name'] = trim("{$data['honorific']} {$data['first_name']} {$data['last_name']}");
        $data['role_title'] = 'Portfolio Coordinator';

        if (! empty($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }

        if ($request->hasFile('coordinator_photo')) {
            try {
                $data['coordinator_photo_path'] = $this->compressAndStoreImage($request->file('coordinator_photo'), 'coordinators');
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'coordinator_photo' => "That photo couldn't be processed ({$e->getMessage()}). Please try a different file.",
                ]);
            }
        }

        $coordinator = Coordinator::create($data);

        VersionHistory::record(
            null,
            'Coordinator Profile',
            'create_coordinator',
            $coordinator->name,
            changes: ChangeLog::initial($coordinator, HistoryFields::coordinator()),
        );

        return redirect()->route('admin.coordinators.index')->with('status', 'Coordinator added successfully.');
    }

    public function update(UpdateCoordinatorRequest $request, Coordinator $coordinator): RedirectResponse
    {
        $data = $request->validated();
        $data['name'] = trim("{$data['honorific']} {$data['first_name']} {$data['last_name']}");
        $data['role_title'] = 'Portfolio Coordinator';

        if (! empty($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }

        if ($request->hasFile('coordinator_photo')) {
            // Compress the new photo *before* touching the old one — if
            // processing fails, the coordinator keeps their existing photo
            // instead of ending up with none at all.
            try {
                $newPhotoPath = $this->compressAndStoreImage($request->file('coordinator_photo'), 'coordinators');
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'coordinator_photo' => "That photo couldn't be processed ({$e->getMessage()}). Please try a different file.",
                ]);
            }

            if ($coordinator->coordinator_photo_path) {
                Storage::disk('public')->delete($coordinator->coordinator_photo_path);
            }
            $data['coordinator_photo_path'] = $newPhotoPath;
        }

        // Which fields this save really changed (see ChangeLog) — a save that
        // changes nothing isn't logged at all.
        $changes = ChangeLog::track($coordinator, HistoryFields::coordinator(), fn () => $coordinator->update($data));

        VersionHistory::recordChanges(null, 'Coordinator Profile', 'update_coordinator', $changes, $coordinator->name);

        return redirect()->route('admin.coordinators.index')->with('status', 'Coordinator updated successfully.');
    }

    public function destroy(Coordinator $coordinator): RedirectResponse
    {
        // Snapshot the name onto every assignment row — Active or already
        // Completed — before coordinator_id gets nulled out from under them
        // (see the migration that added this column + made the FK SET NULL
        // instead of CASCADE). Without this, coordinator_assignments — a
        // startup's whole coordination history — used to be hard-deleted
        // outright the moment its coordinator was; now the rows survive,
        // but would still lose the coordinator's name once coordinator_id
        // has nothing left to look it up by.
        $coordinator->assignments()->update(['coordinator_name_snapshot' => $coordinator->name]);

        // An Active assignment can't sensibly stay "Active" once its
        // coordinator is gone — mirrors sending a still-open roadblock back
        // to Pending below: the startup should read as needing a
        // coordinator again, not as still having one that's now null.
        // Already-Completed assignments are left as Completed — they're
        // closed-out history either way.
        $coordinator->assignments()
            ->where('assignment_status', 'Active')
            ->update(['assignment_status' => 'Inactive']);

        // Same fix as MentorController::destroy() — see its comment. The
        // coordinator_id FK is ON DELETE SET NULL, so without this, any
        // roadblock still assigned to this coordinator would be left stuck
        // as "Scheduled"/"Pending Review" with a blank assignee column
        // instead of reappearing in the Pending list.
        $coordinator->roadblocks()
            ->whereIn('status', Roadblock::ACTIVE_STATUSES)
            ->get()
            ->each(fn (Roadblock $roadblock) => $roadblock->update(Roadblock::pendingResetAttributes()));

        // Same reasoning as MentorController::destroy() — snapshot the name
        // onto closed-out roadblocks before the FK nulls coordinator_id out
        // from under them, so Archive can still say who it was.
        $coordinator->roadblocks()
            ->whereIn('status', ['Resolved', 'Failed', 'Deleted by Admin'])
            ->update(['assignee_name_snapshot' => $coordinator->display_name]);

        if ($coordinator->coordinator_photo_path) {
            Storage::disk('public')->delete($coordinator->coordinator_photo_path);
        }

        $coordinatorName = $coordinator->name;

        $coordinator->delete();

        VersionHistory::record(null, 'Coordinator Profile', 'delete_coordinator', $coordinatorName);

        return redirect()->route('admin.coordinators.index')->with('status', 'Coordinator removed.');
    }
}