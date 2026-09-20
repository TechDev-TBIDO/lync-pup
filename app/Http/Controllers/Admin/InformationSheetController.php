<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncubationInvolvementRequest;
use App\Http\Requests\Admin\StoreLdInterventionRequest;
use App\Http\Requests\Admin\StoreStartupReferenceRequest;
use App\Http\Requests\Admin\StoreTeamMemberRequest;
use App\Http\Requests\Admin\UpdateIncubationInvolvementRequest;
use App\Http\Requests\Admin\UpdateInformationSheetRequest;
use App\Http\Requests\Admin\UpdateLdInterventionRequest;
use App\Http\Requests\Admin\UpdateStartupReferenceRequest;
use App\Http\Requests\Admin\UpdateTeamMemberRequest;
use App\Mail\InformationSheetRejectedMail;
use App\Notifications\InformationSheetApproved;
use App\Notifications\InformationSheetRejected;
use App\Models\Cohort;
use App\Models\IncubationInvolvement;
use App\Models\LdIntervention;
use App\Models\StartupReference;
use App\Models\Startup;
use App\Models\TeamMember;
use App\Models\VersionHistory;
use App\Support\ReadinessRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InformationSheetController extends Controller
{
    public function show(Startup $startup): View
    {
        $startup->load([
            'informationSheet.incubationInvolvements',
            'informationSheet.ldInterventions',
            'informationSheet.references',
            'informationSheet.files',
            'teamMembers',
            'user',
        ]);

        $nameParts = \App\Models\InformationSheet::splitFounderName($startup->user?->name);

        $versionHistory = VersionHistory::where('startup_id', $startup->startup_id)
            ->where('context', 'Information Sheet')
            ->with('user')
            ->latest()
            ->get();

        return view('admin.information-sheets.show', [
            'startup' => $startup,
            'versionHistory' => $versionHistory,
            // Seeds empty fields from the Startup Profile — display only, never
            // written until the sheet itself is saved.
            'prefill' => [
                'surname' => $nameParts['surname'],
                'first_name' => $nameParts['first_name'],
                'middle_name' => $nameParts['middle_name'],
                'mobile_no' => (string) $startup->contact_phone,
                'founder_email' => (string) $startup->user?->email,
            ],
            // Feeds the Accept confirmation's "Assign to Cohort" picker — an
            // optional override only; the startup was already placed into a
            // cohort at email verification (see AssignLatestCohortOnVerification
            // and approve()).
            'cohorts' => Cohort::where('status', 'Active')->orderBy('number')->get(),
            // Same "which pills are still not started" list Venture Exit's own
            // Save gate warns with (see ReadinessRubric::incompleteLabelsFor's
            // docblock) — the Accept & Lock confirmation warns with it too,
            // instead of silently locking a sheet whose founder's Pre/Active/
            // Post assessments were never actually finished.
            'incompleteAssessments' => ReadinessRubric::incompleteLabelsFor($startup),
        ]);
    }

    public function approve(Startup $startup, Request $request): RedirectResponse
    {
        abort_if(
            ! $startup->evaluationReached(),
            403,
            'This startup\'s evaluation must be scheduled and its date reached before their Information Sheet can be approved.'
        );

        // Cohort placement no longer waits for this moment — every startup is
        // already placed into whatever cohort was latest when its founder
        // verified their email (see AssignLatestCohortOnVerification). The
        // "Assign to Cohort" picker on the Accept confirmation is kept only
        // as an optional admin override: pick a different cohort here and
        // this startup moves to it; leave it blank and the cohort assigned
        // at verification stands untouched.
        $data = $request->validate([
            'cohort_id' => ['nullable', 'exists:cohorts,cohort_id'],
        ]);
        $cohort = ! empty($data['cohort_id']) ? Cohort::findOrFail($data['cohort_id']) : null;

        // Captured before the update so re-approving an already-approved sheet
        // (the admin can revisit this action) doesn't re-notify the founder.
        $wasApproved = $startup->hasApprovedInformationSheet();

        $startup->informationSheet()->update([
            'approval_status' => 'Approved',
            // Stamped so the evaluation roster can tell a sheet approved on the
            // evaluation day (DONE) from one signed off later (MISSED) - see
            // EvaluationSchedule::approvedOnEvaluationDay().
            'approved_at' => now(),
            // Clears any rejection history/countdown this startup was under —
            // approval means they're in for good, so the Rejected tab and the
            // founders:purge-expired-rejections command should never see them
            // again unless they're rejected afresh later.
            'rejected_at' => null,
            'evaluator_remarks' => null,
        ]);

        $startup->update([
            'application_decided_at' => now(),
            ...($cohort ? [
                'cohort_id' => $cohort->cohort_id,
                // Kept in sync so every existing "Cohort {{ $startup->cohort_number }}"
                // display elsewhere in the app (dashboard, profile, roadblocks, etc.)
                // continues to work without changes.
                'cohort_number' => $cohort->number,
            ] : []),
        ]);

        if (! $wasApproved) {
            // This is the moment Meeting / Submission / Readiness Result unlock
            // for the founder, so it gets a dashboard card of its own.
            $startup->user?->notify(new InformationSheetApproved);
        }

        VersionHistory::record($startup, 'Information Sheet', 'approve_information_sheet');

        return redirect()
            ->route('admin.assessment-hub.index', ['tab' => 'approved'])
            ->with('status', 'Startup accepted into the incubation program.')
            ->with('just_approved', true);
    }

    /**
     * Rejects the Information Sheet on evaluation day — the founder can
     * still revise and resubmit it (see Startup\InformationSheetController::
     * update()), which then needs its own fresh evaluation before it can be
     * decided again (see Startup::evaluationReached()).
     */
    public function reject(Startup $startup, Request $request): RedirectResponse
    {
        abort_if(
            ! $startup->evaluationReached(),
            403,
            'This startup\'s evaluation must be scheduled and its date reached before their Information Sheet can be rejected.'
        );

        $data = $request->validate([
            'evaluator_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $startup->informationSheet()->update([
            'approval_status' => 'Rejected',
            'approved_at' => null,
            // Starts (or restarts) the 10-day resubmission countdown — see
            // the Rejected tab (_rejected.blade.php) and the
            // founders:purge-expired-rejections command, which auto-deletes
            // any startup still sitting Rejected 10 days after this stamp.
            'rejected_at' => now(),
            'evaluator_remarks' => $data['evaluator_remarks'] ?? null,
        ]);

        $deadline = $startup->refresh()->rejectionDeadline();

        $startup->user?->notify(new InformationSheetRejected(
            $data['evaluator_remarks'] ?? null,
            $deadline,
        ));

        // The notification above only ever produced an in-app dashboard
        // card — founders had no way to find out about a rejection unless
        // they happened to log back in. This is the actual email.
        if ($startup->user?->email) {
            Mail::to($startup->user->email)->send(new InformationSheetRejectedMail(
                $startup->user->name ?? 'Founder',
                $startup->company_name,
                $data['evaluator_remarks'] ?? null,
                $deadline,
            ));
        }

        VersionHistory::record($startup, 'Information Sheet', 'reject_information_sheet');

        return redirect()
            ->route('admin.assessment-hub.index', ['tab' => 'rejected'])
            ->with('status', 'Information sheet rejected. The founder has 10 days to revise and resubmit it.');
    }

    public function update(UpdateInformationSheetRequest $request, Startup $startup): RedirectResponse
    {
        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);

        // Approval only locks the founder out (see Startup\InformationSheetController)
        // — an admin can still revise a sheet after it's approved, e.g. to fix a
        // typo the founder reported after the fact.
        // A filled column is never overwritten with a blank (see InformationSheet::withoutBlanking()).
        $data = $sheet->withoutBlanking($request->validated());

        // Admin edits are corrections made on the founder's behalf, so they
        // must not re-date the founder's declaration. Only fill it when the
        // sheet has never been accomplished.
        if (! $sheet->date_accomplished) {
            $data['date_accomplished'] = now();
        }

        $sheet->update($data);

        VersionHistory::record($startup, 'Information Sheet', 'update_information_sheet');

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Information Sheet updated.');
    }

    // Team Members
    public function storeTeamMember(StoreTeamMemberRequest $request, Startup $startup): RedirectResponse
    {
        $startup->teamMembers()->create($request->validated());

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Team member added.');
    }

    public function updateTeamMember(UpdateTeamMemberRequest $request, TeamMember $teamMember): RedirectResponse
    {
        $teamMember->update($request->validated());

        return redirect()->route('admin.information-sheet.show', $teamMember->startup)->with('status', 'Team member updated.');
    }

    public function destroyTeamMember(TeamMember $teamMember): RedirectResponse
    {
        $startup = $teamMember->startup;
        $teamMember->delete();

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Team member removed.');
    }

    // Incubation Involvement
    public function storeIncubation(StoreIncubationInvolvementRequest $request, Startup $startup): RedirectResponse
    {
        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);
        $sheet->incubationInvolvements()->create($request->validated());

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Incubation involvement added.');
    }

    public function updateIncubation(UpdateIncubationInvolvementRequest $request, IncubationInvolvement $incubationInvolvement): RedirectResponse
    {
        $incubationInvolvement->update($request->validated());

        return redirect()->route('admin.information-sheet.show', $incubationInvolvement->informationSheet->startup)->with('status', 'Updated.');
    }

    public function destroyIncubation(IncubationInvolvement $incubationInvolvement): RedirectResponse
    {
        $startup = $incubationInvolvement->informationSheet->startup;
        $incubationInvolvement->delete();

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Removed.');
    }

    // L&D Interventions
    public function storeLd(StoreLdInterventionRequest $request, Startup $startup): RedirectResponse
    {
        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);
        $sheet->ldInterventions()->create($request->validated());

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'L&D intervention added.');
    }

    public function updateLd(UpdateLdInterventionRequest $request, LdIntervention $ldIntervention): RedirectResponse
    {
        $ldIntervention->update($request->validated());

        return redirect()->route('admin.information-sheet.show', $ldIntervention->informationSheet->startup)->with('status', 'Updated.');
    }

    public function destroyLd(LdIntervention $ldIntervention): RedirectResponse
    {
        $startup = $ldIntervention->informationSheet->startup;
        $ldIntervention->delete();

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Removed.');
    }

    // References
    public function storeReference(StoreStartupReferenceRequest $request, Startup $startup): RedirectResponse
    {
        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);
        $sheet->references()->create($request->validated());

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Reference added.');
    }

    public function updateReference(UpdateStartupReferenceRequest $request, StartupReference $reference): RedirectResponse
    {
        $reference->update($request->validated());

        return redirect()->route('admin.information-sheet.show', $reference->informationSheet->startup)->with('status', 'Updated.');
    }

    public function destroyReference(StartupReference $reference): RedirectResponse
    {
        $startup = $reference->informationSheet->startup;
        $reference->delete();

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Removed.');
    }
}
