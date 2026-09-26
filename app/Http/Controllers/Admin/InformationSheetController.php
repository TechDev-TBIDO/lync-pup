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
use App\Support\ChangeLog;
use App\Support\HistoryFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InformationSheetController extends Controller
{
    /**
     * The Save button posts the sheet and then every table row (Core Team,
     * Incubation, L&D, References) as its own request, back to back. Changes
     * made by those requests are folded into the one "Edited Information
     * Sheet" entry that Save started, if it is at most this many seconds old
     * — see VersionHistory::recordChanges().
     */
    private const SAVE_MERGE_SECONDS = 30;

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

        $nameParts = $startup->user?->founderNameParts()
            ?? \App\Models\InformationSheet::splitFounderName(null);

        $versionHistory = VersionHistory::where('startup_id', $startup->startup_id)
            ->where('context', 'Information Sheet')
            ->with('user')
            ->newestFirst()
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
        ]);
    }

    public function approve(Startup $startup, Request $request): RedirectResponse
    {
        abort_if(
            ! $startup->evaluationReached(),
            403,
            'This startup\'s evaluation must be scheduled and its date reached before their Information Sheet can be approved.'
        );

        // Same fields the Endorsement and Approval section (36. DECLARATION,
        // admin.information-sheets.show) marks required — Portfolio Manager
        // is the one exception, since a startup can be endorsed before a
        // Portfolio Coordinator is assigned. The page's own JS (tryApprove())
        // already blocks the Accept & Lock click while these are blank, but
        // that's client-side only; this is the actual gate, in case that's
        // ever bypassed.
        $sheet = $startup->informationSheet;
        $missingEndorsementFields = collect([
            'cohort_no' => 'Cohort No.',
            // Endorsed By and its Date are optional (InformationSheet::OPTIONAL_FIELDS).
            'director_approval_date' => 'Date of Approval',
        ])->filter(fn ($label, $field) => blank($sheet?->{$field}))->values();

        if ($missingEndorsementFields->isNotEmpty()) {
            return back()->withErrors([
                'endorsement' => 'Fill in '.$missingEndorsementFields->implode(', ').' (under Endorsement and Approval) before accepting & locking this sheet.',
            ]);
        }

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

        // For the Edit History entry: the decision fields (and the startup's
        // cohort, which the optional override above can move) as they were.
        $decisionFields = HistoryFields::informationSheetDecision();
        $decisionBefore = ChangeLog::snapshot($startup->informationSheet()->first(), $decisionFields);
        $cohortBefore = $startup->cohort_number;

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

        VersionHistory::record(
            $startup,
            'Information Sheet',
            'approve_information_sheet',
            changes: [
                ...ChangeLog::diff($decisionBefore, ChangeLog::snapshot($startup->informationSheet()->first(), $decisionFields), $decisionFields),
                ...ChangeLog::field(
                    'Cohort',
                    $cohortBefore ? "Cohort {$cohortBefore}" : null,
                    $startup->cohort_number ? "Cohort {$startup->cohort_number}" : null,
                ),
            ],
        );

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

        // Captured before the update, same guard approve() already uses for
        // its own notification — without this, the "Yes, reject" button (no
        // disabling, no loading state on click) sends a fresh rejection
        // email and in-app notification on every single request, so a
        // double-click or a second click on a slow connection emails the
        // founder twice for the one rejection.
        $wasRejected = $startup->informationSheet?->approval_status === 'Rejected';

        $decisionFields = HistoryFields::informationSheetDecision();
        $decisionBefore = ChangeLog::snapshot($startup->informationSheet()->first(), $decisionFields);

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

        if (! $wasRejected) {
            $startup->user?->notify(new InformationSheetRejected(
                $data['evaluator_remarks'] ?? null,
                $deadline,
            ));

            // The notification above only ever produced an in-app dashboard
            // card — founders had no way to find out about a rejection
            // unless they happened to log back in. This is the actual email.
            if ($startup->user?->email) {
                Mail::to($startup->user->email)->send(new InformationSheetRejectedMail(
                    $startup->user->name ?? 'Founder',
                    $startup->company_name,
                    $data['evaluator_remarks'] ?? null,
                    $deadline,
                ));
            }
        }

        VersionHistory::record(
            $startup,
            'Information Sheet',
            'reject_information_sheet',
            changes: ChangeLog::diff($decisionBefore, ChangeLog::snapshot($startup->informationSheet()->first(), $decisionFields), $decisionFields),
        );

        return redirect()
            ->route('admin.assessment-hub.index', ['tab' => 'rejected'])
            ->with('status', 'Information sheet rejected. The founder has 10 days to revise and resubmit it.');
    }

    public function update(UpdateInformationSheetRequest $request, Startup $startup): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

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

        // Which of the sheet's fields this save really changed (see
        // ChangeLog) — one that changes nothing isn't logged at all.
        $changes = ChangeLog::track($sheet, HistoryFields::informationSheet(), fn () => $sheet->update($data));

        $this->logSheetChanges($startup, $changes);

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Information Sheet updated.');
    }

    // Team Members
    public function storeTeamMember(StoreTeamMemberRequest $request, Startup $startup): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $member = $startup->teamMembers()->create($request->validated());

        $this->logSheetChanges($startup, ChangeLog::note('Core Team · Added '.$this->rowName($member->full_name)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Team member added.');
    }

    public function updateTeamMember(UpdateTeamMemberRequest $request, TeamMember $teamMember): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $changes = ChangeLog::track($teamMember, HistoryFields::teamMember(), fn () => $teamMember->update($request->validated()));

        $this->logSheetChanges($teamMember->startup, ChangeLog::prefixed($changes, 'Core Team · '.$this->rowName($teamMember->full_name)));

        return redirect()->route('admin.information-sheet.show', $teamMember->startup)->with('status', 'Team member updated.');
    }

    public function destroyTeamMember(TeamMember $teamMember): RedirectResponse
    {
        $startup = $teamMember->startup;
        $teamMember->delete();

        $this->logSheetChanges($startup, ChangeLog::note('Core Team · Removed '.$this->rowName($teamMember->full_name)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Team member removed.');
    }

    // Incubation Involvement
    public function storeIncubation(StoreIncubationInvolvementRequest $request, Startup $startup): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);
        $row = $sheet->incubationInvolvements()->create($request->validated());

        $this->logSheetChanges($startup, ChangeLog::note('Incubation Involvement · Added '.$this->rowName($row->organization_name_address)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Incubation involvement added.');
    }

    public function updateIncubation(UpdateIncubationInvolvementRequest $request, IncubationInvolvement $incubationInvolvement): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $changes = ChangeLog::track($incubationInvolvement, HistoryFields::incubationInvolvement(), fn () => $incubationInvolvement->update($request->validated()));

        $this->logSheetChanges(
            $incubationInvolvement->informationSheet->startup,
            ChangeLog::prefixed($changes, 'Incubation Involvement · '.$this->rowName($incubationInvolvement->organization_name_address)),
        );

        return redirect()->route('admin.information-sheet.show', $incubationInvolvement->informationSheet->startup)->with('status', 'Updated.');
    }

    public function destroyIncubation(IncubationInvolvement $incubationInvolvement): RedirectResponse
    {
        $startup = $incubationInvolvement->informationSheet->startup;
        $incubationInvolvement->delete();

        $this->logSheetChanges($startup, ChangeLog::note('Incubation Involvement · Removed '.$this->rowName($incubationInvolvement->organization_name_address)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Removed.');
    }

    // L&D Interventions
    public function storeLd(StoreLdInterventionRequest $request, Startup $startup): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);
        $row = $sheet->ldInterventions()->create($request->validated());

        $this->logSheetChanges($startup, ChangeLog::note('L&D Intervention · Added '.$this->rowName($row->title)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'L&D intervention added.');
    }

    public function updateLd(UpdateLdInterventionRequest $request, LdIntervention $ldIntervention): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $changes = ChangeLog::track($ldIntervention, HistoryFields::ldIntervention(), fn () => $ldIntervention->update($request->validated()));

        $this->logSheetChanges(
            $ldIntervention->informationSheet->startup,
            ChangeLog::prefixed($changes, 'L&D Intervention · '.$this->rowName($ldIntervention->title)),
        );

        return redirect()->route('admin.information-sheet.show', $ldIntervention->informationSheet->startup)->with('status', 'Updated.');
    }

    public function destroyLd(LdIntervention $ldIntervention): RedirectResponse
    {
        $startup = $ldIntervention->informationSheet->startup;
        $ldIntervention->delete();

        $this->logSheetChanges($startup, ChangeLog::note('L&D Intervention · Removed '.$this->rowName($ldIntervention->title)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Removed.');
    }

    // References
    public function storeReference(StoreStartupReferenceRequest $request, Startup $startup): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);
        $row = $sheet->references()->create($request->validated());

        $this->logSheetChanges($startup, ChangeLog::note('Reference · Added '.$this->rowName($row->name)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Reference added.');
    }

    public function updateReference(UpdateStartupReferenceRequest $request, StartupReference $reference): RedirectResponse|Response
    {
        // Validation-only pass used by the page's all-or-nothing Save (see
        // submitInfoSheetForms in admin/information-sheets/show.blade.php): every
        // row is dry-run first so ALL rows' errors come back together, and
        // nothing is persisted until every section validates. Runs after the
        // form request's own validation, so an invalid field still fails it.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $changes = ChangeLog::track($reference, HistoryFields::startupReference(), fn () => $reference->update($request->validated()));

        $this->logSheetChanges(
            $reference->informationSheet->startup,
            ChangeLog::prefixed($changes, 'Reference · '.$this->rowName($reference->name)),
        );

        return redirect()->route('admin.information-sheet.show', $reference->informationSheet->startup)->with('status', 'Updated.');
    }

    public function destroyReference(StartupReference $reference): RedirectResponse
    {
        $startup = $reference->informationSheet->startup;
        $reference->delete();

        $this->logSheetChanges($startup, ChangeLog::note('Reference · Removed '.$this->rowName($reference->name)));

        return redirect()->route('admin.information-sheet.show', $startup)->with('status', 'Removed.');
    }

    /**
     * Logs what a save changed on this startup's Information Sheet — the
     * sheet's own fields, or one row of one of its tables — under the one
     * "Edited Information Sheet" entry per Save (see SAVE_MERGE_SECONDS).
     * Nothing is logged when nothing changed.
     *
     * @param  list<array<string, string|null>>  $changes
     */
    private function logSheetChanges(?Startup $startup, array $changes): void
    {
        VersionHistory::recordChanges(
            $startup,
            'Information Sheet',
            'update_information_sheet',
            $changes,
            mergeWithinSeconds: self::SAVE_MERGE_SECONDS,
        );
    }

    /**
     * How a table row is named in the history ("Juan Dela Cruz"), cut short
     * so a long organization name or title can't crowd the line.
     */
    private function rowName(?string $value): string
    {
        $value = ChangeLog::preview((string) $value);

        return $value === '' ? 'a blank row' : Str::limit($value, 40);
    }
}
