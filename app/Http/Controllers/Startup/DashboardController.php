<?php

namespace App\Http\Controllers\Startup;

use App\Http\Controllers\Controller;
use App\Models\AssessmentDocument;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Support\ReadinessRubric;
use App\Support\VentureExitForm;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $startup = auth()->user()->startup;

        if (! $startup) {
            return view('startup.dashboard', ['startup' => null]);
        }

        $startup->loadMissing(['informationSheet', 'activeCoordinatorAssignment']);

        // "Cohort 3 - 001": no per-cohort sequence exists anywhere in the
        // app yet, so it's derived here — this startup's rank by startup_id
        // among every startup sharing the same cohort_number.
        $cohortSequence = Startup::where('cohort_number', $startup->cohort_number)
            ->where('startup_id', '<=', $startup->startup_id)
            ->count();

        // Prefer Post-Assessment once it exists (it supersedes Pre), else
        // fall back to Pre-Assessment, else there's simply nothing to show.
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Post-Assessment')
            ->first();
        $readinessStage = $assessment ? 'Post-Assessment' : null;

        if (! $assessment) {
            $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
                ->where('stage', 'Pre-Assessment')
                ->first();
            $readinessStage = $assessment ? 'Pre-Assessment' : null;
        }

        $onboardingSteps = $this->onboardingSteps($startup);

        // A rejected sheet gets its own dedicated notice card (see the view)
        // rather than falling into the generic "awaiting approval" one below
        // — it needs to actually name the resubmission deadline.
        $isRejected = $startup->isRejectedPendingResubmission();
        $rejectionDeadline = $startup->rejectionDeadline();

        return view('startup.dashboard', [
            'startup' => $startup,
            'cohortSequence' => $cohortSequence,
            'assessment' => $assessment,
            'readinessStage' => $readinessStage,
            'overallLabel' => ReadinessRubric::overallLabel($assessment->overall_score ?? null),
            'needsProfileSetup' => ! $startup->isProfileComplete(),
            'needsInformationSheet' => $startup->isProfileComplete() && ! $startup->hasSubmittedInformationSheet(),
            'isRejected' => $isRejected,
            'rejectionDeadline' => $rejectionDeadline,
            // Stage 2 -> 3 waiting room: the sheet is in, the remaining
            // modules are still locked (see EnsureFounderStage), so the
            // dashboard says why rather than leaving the founder guessing.
            // Excludes a Rejected sheet — that gets its own notice card
            // above instead of the generic "awaiting approval" one.
            'awaitingSheetApproval' => $startup->isProfileComplete()
                && $startup->hasSubmittedInformationSheet()
                && ! $startup->hasApprovedInformationSheet()
                && ! $isRejected,
            'onboardingSteps' => $onboardingSteps,
            'graduationSteps' => $this->graduationSteps($startup),
            'onboardingComplete' => collect($onboardingSteps)->last()['state'] === 'done',
            'updates' => $this->updates(),
        ]);
    }

    /**
     * The dashboard's "what's new" cards: unread notifications written at the
     * moment an admin actually changed something (see App\Notifications\*),
     * rather than conditions re-derived on every page load. Opening a card
     * marks that row read, so each one clears itself once it has been seen.
     *
     * Capped at three so a founder returning after a long absence gets a
     * readable dashboard rather than a wall of cards.
     */
    protected function updates(): array
    {
        return auth()->user()
            ->unreadNotifications()
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($note) => [
                'id' => $note->id,
                'title' => $note->data['title'] ?? 'Update',
                'body' => $note->data['body'] ?? '',
                'action' => $note->data['action'] ?? 'View',
                'icon' => $note->data['icon'] ?? 'info-sheet.svg',
            ])
            ->all();
    }

    /**
     * "Admin Review" has no dedicated status of its own anywhere in the data
     * model (InformationSheet.approval_status is only Pending/Approved/
     * Rejected) — a scheduled evaluation is used as the concrete signal
     * that review has actually happened, since admins only get there after
     * looking at the sheet.
     */
    protected function onboardingSteps(Startup $startup): array
    {
        $sheet = $startup->informationSheet;
        $approved = $sheet && $sheet->approval_status === 'Approved';

        return $this->stepsWithState([
            'Setup Startup Profile' => $startup->isProfileComplete(),
            'Completed Information Sheet' => $startup->hasSubmittedInformationSheet(),
            'Admin Review' => $startup->hasScheduledEvaluation() || $approved,
            'Schedule for Evaluation' => $startup->hasScheduledEvaluation(),
            'Approved' => $approved,
        ]);
    }

    /**
     * Mirrors how the admin Assessment Hub itself derives progress — there
     * is no "current stage" field on Startup anywhere; it's entirely
     * presence-of-rows-driven (ReadinessLevelAssessment per stage,
     * AssessmentDocument per stage+document_number).
     *
     * Each step is judged on its own merits, and the tracker (see
     * stepsWithSkips()) then shows a stage the admin bypassed as "skipped"
     * rather than freezing on it:
     *  - Pre/Post RL Documents need all four RL types (TRL/MRL/TMRL/SRL)
     *    scored, not just one.
     *  - Venture Exit needs an Exit Status of Graduated or Completed — a
     *    saved but otherwise blank exit form doesn't count.
     */
    protected function graduationSteps(Startup $startup): array
    {
        $preRl = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')->first();
        $postRl = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Post-Assessment')->first();
        $activeDocsCount = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->whereIn('document_number', [6, 7, 8])
            ->count();
        $exitDocument = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Venture Exit')
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->first();
        $exited = in_array(data_get($exitDocument?->data, 'exit_status'), ['Graduated', 'Completed'], true);

        return $this->stepsWithSkips([
            'Active Startup' => $startup->status === 'Active',
            'Pre RL Documents' => (bool) $preRl?->isFullyScored(),
            'Active Documents' => $activeDocsCount >= 3,
            'Post RL Documents' => (bool) $postRl?->isFullyScored(),
            'Venture Exit' => $exited,
        ]);
    }

    /**
     * Turns an ordered [label => isDone] map into the step list the
     * <x-step-tracker> component expects: every step up to the first
     * incomplete one is "done", that first incomplete one is "current",
     * everything after is "upcoming" — forced, even if that later step's
     * own isDone happens to be true. Real milestones (a scheduled
     * evaluation, a submitted RL document, etc.) can exist independently
     * of each other in the data, but the tracker is a linear narrative, so
     * once an earlier step is incomplete every later step displays as not
     * yet reached rather than jumping ahead.
     */
    protected function stepsWithState(array $doneMap): array
    {
        $currentFound = false;
        $steps = [];
        $number = 0;

        foreach ($doneMap as $label => $isDone) {
            $number++;

            if ($isDone && ! $currentFound) {
                $state = 'done';
            } elseif (! $currentFound) {
                $state = 'current';
                $currentFound = true;
            } else {
                $state = 'upcoming';
            }

            $steps[] = ['label' => $label, 'state' => $state, 'number' => $number];
        }

        return $steps;
    }

    /**
     * Like stepsWithState(), but not forced to be strictly linear — for the
     * Graduation Roadmap, where an admin can legitimately skip a stage (e.g.
     * go straight to Active Assessment without a Pre-Assessment).
     *
     * A step that is done is "done" wherever it sits. An unfinished step that
     * comes BEFORE the furthest finished one is "skipped" (it was bypassed,
     * and the tracker shouldn't stay parked on it). The first unfinished step
     * after the furthest finished one is "current", everything beyond it
     * "upcoming". With nothing finished yet, the first step is current.
     */
    protected function stepsWithSkips(array $doneMap): array
    {
        $labels = array_keys($doneMap);
        $furthestDone = -1;

        foreach (array_values($doneMap) as $i => $isDone) {
            if ($isDone) {
                $furthestDone = $i;
            }
        }

        $steps = [];

        foreach ($labels as $i => $label) {
            $state = match (true) {
                (bool) $doneMap[$label] => 'done',
                $i < $furthestDone => 'skipped',
                $i === $furthestDone + 1 => 'current',
                default => 'upcoming',
            };

            $steps[] = ['label' => $label, 'state' => $state, 'number' => $i + 1];
        }

        return $steps;
    }
}
