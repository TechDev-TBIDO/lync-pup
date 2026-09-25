<?php

namespace App\Models;

use App\Support\VentureExitForm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Startup extends Model
{
    use HasFactory;

    protected $primaryKey = 'startup_id';

    /**
     * The Venture Exit form's Exit Status values that mean a startup has
     * left the program — see getExitStatusAttribute() and the
     * scopeGraduated()/scopeCompleted() below, and the exclusion added to
     * scopeActive()/scopeNeedsCoordinator() further down. Mirrors the same
     * two literals already read independently (before this existed) by
     * WelcomeController and DashboardController::graduationSteps().
     */
    public const EXIT_STATUSES = ['Graduated', 'Completed'];

    protected $fillable = [
        'user_id', 'company_name', 'industry_sector', 'business_description', 'cohort_number',
        'contact_phone', 'location', 'website', 'startup_photo_path', 'pitch_deck_requested_at',
        'cohort_id', 'admin_remarks', 'rejection_reason', 'application_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'pitch_deck_requested_at' => 'datetime',
            'application_decided_at' => 'datetime',
        ];
    }

    public function getBatchLabelAttribute(): string
    {
        return "Cohort {$this->cohort_number}";
    }

    /**
     * Public URL for the uploaded startup logo/photo, or null when none has
     * been uploaded — callers (e.g. the mentorship table's avatar) fall back
     * to an initials badge in that case.
     */
    public function getStartupPhotoUrlAttribute(): ?string
    {
        return $this->startup_photo_path
            ? Storage::disk('public')->url($this->startup_photo_path)
            : null;
    }

    /**
     * Synthetic reference shown on the Founder Application review/view
     * screens, e.g. "APP-2026-00031". Not stored — derived from the
     * registration year and the startup's own primary key.
     */
    public function getApplicationIdAttribute(): string
    {
        $year = $this->created_at?->format('Y') ?? now()->format('Y');

        return 'APP-'.$year.'-'.str_pad((string) $this->startup_id, 5, '0', STR_PAD_LEFT);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class, 'cohort_id', 'cohort_id');
    }

    public function informationSheet()
    {
        return $this->hasOne(InformationSheet::class, 'startup_id');
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class, 'startup_id');
    }

    /**
     * The Startup Profile page's own Core Team roster (see migration
     * 000049) - separate from teamMembers() above, which belongs to the
     * Information Sheet. Only ever seeds teamMembers() once, while that
     * one's still empty (see StartupProfileController::update()).
     */
    public function startupTeamMembers()
    {
        return $this->hasMany(StartupTeamMember::class, 'startup_id');
    }

    public function readinessAssessments()
    {
        return $this->hasMany(ReadinessLevelAssessment::class, 'startup_id');
    }

    /**
     * The assessment whose overall RL score is shown as "RLS x.x" (landing
     * page cards, admin startup cards, founder profile):
     *   - Post-Assessment, if it has an overall score;
     *   - otherwise Pre-Assessment, if it has one;
     *   - otherwise nothing (null), so no RLS is shown.
     * A Post-Assessment row that exists but was emptied (no score) no longer
     * hides the Pre-Assessment score, which is what latestOfMany('assessment_date')
     * used to do. For a hasOne, both lazy and eager loading take the first
     * row in this order, so the priority below is what decides.
     */
    public function latestReadinessAssessment()
    {
        return $this->hasOne(ReadinessLevelAssessment::class, 'startup_id')
            ->whereNotNull('overall_score')
            ->orderByRaw("CASE stage WHEN 'Post-Assessment' THEN 0 WHEN 'Pre-Assessment' THEN 1 ELSE 2 END")
            ->orderByDesc('assessment_date');
    }

    /**
     * Specifically the Pre-Assessment stage's row — unlike
     * latestReadinessAssessment() above, which is whichever stage was
     * scored most recently (Post-Assessment, once it exists, since it
     * always comes chronologically after Pre-Assessment). Added because the
     * admin Startup Profile page's "Readiness Level" card shows the Pre- and
     * Post-Assessment rows separately (via a dropdown), so it needs each
     * stage's actual row, not whatever's newest overall.
     */
    public function preAssessment()
    {
        return $this->hasOne(ReadinessLevelAssessment::class, 'startup_id')->where('stage', 'Pre-Assessment');
    }

    /**
     * Specifically the Post-Assessment stage's row. Counterpart to
     * preAssessment() above, so the admin Startup Profile page's "Readiness
     * Level" card can offer a Pre-/Post-Assessment dropdown.
     */
    public function postAssessment()
    {
        return $this->hasOne(ReadinessLevelAssessment::class, 'startup_id')->where('stage', 'Post-Assessment');
    }

    public function coordinatorAssignments()
    {
        return $this->hasMany(CoordinatorAssignment::class, 'startup_id');
    }

    public function activeCoordinatorAssignment()
    {
        return $this->hasOne(CoordinatorAssignment::class, 'startup_id')->where('assignment_status', 'Active');
    }

    /**
     * The Venture Exit stage's one-off exit form (see App\Support\
     * VentureExitForm) — specifically document_number 13 under stage
     * 'Venture Exit'. Its `exit_status` field is what
     * getExitStatusAttribute() below reads to decide whether this startup
     * has left the program.
     */
    public function ventureExitDocument()
    {
        return $this->hasOne(AssessmentDocument::class, 'startup_id')
            ->where('stage', 'Venture Exit')
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER);
    }

    /**
     * 'Graduated', 'Completed', or null — the Venture Exit form's Exit
     * Status field, read the same way WelcomeController and
     * DashboardController::graduationSteps() already do independently: a
     * form that's merely filled in, with no Exit Status chosen (or set to
     * anything else), leaves the startup un-exited. Drives the Startup
     * Profile page's Graduated/Completed summary cards, tags and tabs (see
     * scopeGraduated()/scopeCompleted() below) and takes priority in
     * getStatusAttribute() over the usual Active/Assign Coordinator/etc.
     * status once set.
     */
    public function getExitStatusAttribute(): ?string
    {
        $status = data_get($this->ventureExitDocument?->data, 'exit_status');

        return in_array($status, self::EXIT_STATUSES, true) ? $status : null;
    }

    public function roadblocks()
    {   
    return $this->hasMany(Roadblock::class, 'startup_id', 'startup_id');
    }

    public function evaluationSchedules()
    {
        return $this->hasMany(EvaluationSchedule::class, 'startup_id', 'startup_id');
    }

    public function assessmentMeetings()
    {
        return $this->hasMany(AssessmentMeeting::class, 'startup_id', 'startup_id');
    }

    public function savedReports()
    {
        return $this->hasMany(SavedReport::class, 'startup_id', 'startup_id');
    }

    public function latestEvaluationSchedule()
    {
        return $this->hasOne(EvaluationSchedule::class, 'startup_id', 'startup_id')->latestOfMany('evaluation_date');
    }

    public function getEvaluationStatusAttribute(): string
    {
        $latest = $this->latestEvaluationSchedule;

        if (! $latest) {
            return 'Not Started';
        }

        return $latest->status === 'Completed' ? 'Completed' : 'In Progress';
    }

    /**
     * True once an admin has booked (and not cancelled) an evaluation for
     * this startup. Once this is true, the founder's/admin's Information
     * Sheet save should stop treating blank fields as "clear this value" —
     * see InformationSheet::blankedFields().
     */
    public function hasScheduledEvaluation(): bool
    {
        return $this->evaluationSchedules()->where('status', '!=', 'Cancelled')->exists();
    }

    /**
     * True once the LATEST (non-cancelled) evaluation's date has actually
     * arrived — today or in the past — as opposed to hasScheduledEvaluation()
     * above, which is satisfied by merely booking a future day. Accept/Reject
     * gates on this one: a startup only sitting in the Upcoming list
     * shouldn't be decidable yet, since nobody has evaluated it.
     *
     * Deliberately scoped to the LATEST schedule, and deliberately checked
     * against the sheet's own rejected_at: after a Reject, the founder can
     * edit and resubmit, and that resubmission needs its own fresh
     * evaluation before it can be Accepted/Rejected again — reusing the
     * old, already-decided evaluation would let an admin re-decide a
     * resubmission nobody has actually re-evaluated.
     *
     * This used to compare evaluation_date against submission_date instead —
     * both DATE-only columns — so a same-day reject-then-resubmit (the
     * founder revising and resubmitting within the same day, easily the most
     * common real sequence) compared "today" against "today" and never
     * caught the staleness at all.
     *
     * rejected_at is a real timestamp, so it's compared against the
     * schedule's own updated_at (also a real timestamp) instead: a schedule
     * that hasn't been touched since the rejection is the stale one.
     * Rescheduling that same row (see Reschedule elsewhere in the app)
     * naturally bumps its updated_at past rejected_at and makes it count
     * again, same as approving clears rejected_at back to null.
     *
     * Gated on approval_status still being Pending, not merely on rejected_at
     * being set: rejected_at itself is stamped by the SAME reject() call that
     * makes this method's caller check it in the first place (see
     * InformationSheetController::reject()'s abort_if), and it's never
     * cleared just by resubmitting — only by a later Approve. Without the
     * Pending guard, a straight reject() with no resubmission at all would
     * immediately (and wrongly) invalidate the very evaluation that just
     * decided it, since the schedule obviously predates a rejected_at
     * stamped microseconds ago.
     */
    public function evaluationReached(): bool
    {
        $latest = $this->latestEvaluationSchedule;

        if (! $latest || $latest->status === 'Cancelled') {
            return false;
        }

        $sheet = $this->informationSheet;

        if ($sheet?->approval_status === 'Pending' && $sheet->rejected_at && $latest->updated_at->lt($sheet->rejected_at)) {
            return false;
        }

        return $latest->evaluation_date->lte(now()->toDateString());
    }

    /**
     * True only on the calendar day of a (non-cancelled) evaluation — drives
     * the founder-side Information Sheet lock, so the sheet is frozen while
     * the evaluators are reading it and reopens by itself the next morning.
     *
     * Deliberately the day itself and not "from the day onward": an
     * evaluation nobody attended used to lock the founder out permanently,
     * with no path back except a coordinator noticing the Missed tab. A
     * missed evaluation now simply releases the sheet.
     *
     * Admin retains edit access throughout, and an Approved sheet stays
     * locked on its own separate check.
     */
    public function evaluationDayLockActive(): bool
    {
        return $this->evaluationSchedules()
            ->where('status', '!=', 'Cancelled')
            ->whereDate('evaluation_date', now()->toDateString())
            ->exists();
    }

    // Computed status, not stored
    public function getStatusAttribute(): string
    {
        // Exit status wins over everything else: once the Venture Exit
        // form's Exit Status is set, the startup has left the program and
        // is no longer Active/Assign Coordinator/etc. — see
        // getExitStatusAttribute() above.
        if ($this->exit_status) {
            return $this->exit_status;
        }

        $sheet = $this->informationSheet;

        if ($sheet && $sheet->approval_status === 'Rejected') {
            return 'Rejected';
        }

        if ($sheet && $sheet->approval_status === 'Approved') {
            return $this->activeCoordinatorAssignment ? 'Active' : 'Assign Coordinator';
        }

        // Not yet decided (no sheet, or approval_status still 'Pending'):
        // per direct testing feedback, split into Applicant vs Pending
        // based on whether the startup has actually finished Profile Setup
        // + the Information Sheet AND been scheduled for evaluation. Labeled
        // "Applicant" (not "Onboarding") since nothing here has actually been
        // accepted yet — that only happens once the Information Sheet is
        // evaluated and approved.
        return $this->isReadyForEvaluation() ? 'Pending' : 'Applicant';
    }

    /**
     * True once the Startup Profile itself (company name, founder name,
     * industry, location, phone, photo) has all of its required fields
     * filled in — the gate for the founder-side Information Sheet module
     * (see InformationSheetController) and the first step of the founder
     * dashboard's "Startup Onboarding" tracker. Deliberately excludes
     * business_description (required on the Profile form itself, but not
     * part of this particular gate) and the Information Sheet's own
     * submission_date, which belongs to the module this gates, not the
     * profile itself.
     */
    public function isProfileComplete(): bool
    {
        return filled($this->company_name)
            && filled($this->industry_sector)
            && filled($this->user?->name)
            && filled($this->contact_phone)
            && filled($this->location)
            && filled($this->startup_photo_path);
    }

    /**
     * True once the founder has actually submitted the Information Sheet.
     * submission_date gets stamped every time InformationSheetController
     * ::update() saves it — deliberately NOT just "a row exists", since a
     * blank row is created as soon as the Startup Profile is saved (see
     * StartupProfileController::update()'s updateOrCreate).
     */
    public function hasSubmittedInformationSheet(): bool
    {
        return filled($this->informationSheet?->submission_date);
    }

    /**
     * Information Sheet progress for the Assessment Hub's "Awaiting
     * Schedule" list: 'Not Started' (no sheet row at all yet — the founder
     * hasn't opened it), 'In Progress' (a row exists — created the moment a
     * founder saves their Startup Profile — but they haven't submitted it),
     * 'Re-Evaluation' (submitted, currently Pending, but this sheet carries
     * rejected_at from an earlier rejection — i.e. a resubmission after
     * being rejected, not a first-time submission), or 'Completed' (a
     * genuine first-time submission; see hasSubmittedInformationSheet()).
     * 'Completed' and 'Re-Evaluation' are both ready to have an evaluation
     * scheduled against them.
     *
     * A resubmission flips approval_status back to 'Pending' on its own
     * (see Startup\InformationSheetController::update()) but deliberately
     * never clears rejected_at — only Admin\InformationSheetController's
     * approve()/reject() touch that column — so its presence here is what
     * distinguishes "resubmitted after rejection" from "never rejected".
     */
    public function informationSheetStatus(): string
    {
        if (! $this->informationSheet) {
            return 'Not Started';
        }

        if (! $this->hasSubmittedInformationSheet()) {
            return 'In Progress';
        }

        if ($this->informationSheet->approval_status === 'Pending' && $this->informationSheet->rejected_at) {
            return 'Re-Evaluation';
        }

        return 'Completed';
    }

    /**
     * True once an admin has approved the Information Sheet. This is the
     * gate for the founder's remaining modules (Meeting, Submission,
     * Readiness Result) — see App\Http\Middleware\EnsureFounderStage and
     * the nav lock state in components/layouts/founder.blade.php.
     */
    public function hasApprovedInformationSheet(): bool
    {
        return $this->informationSheet?->approval_status === 'Approved';
    }

    /**
     * True while this startup's Information Sheet is sitting Rejected and
     * hasn't been resubmitted or approved yet — the exact set the
     * Assessment Hub's Rejected tab lists and the
     * founders:purge-expired-rejections command targets for auto-deletion.
     * Resubmitting flips approval_status back to 'Pending' on its own (see
     * Startup\InformationSheetController::update()), which takes a startup
     * out of this state without any extra bookkeeping here.
     */
    public function isRejectedPendingResubmission(): bool
    {
        return $this->informationSheet?->approval_status === 'Rejected';
    }

    /**
     * The date-time by which a Rejected startup must resubmit before
     * founders:purge-expired-rejections removes it — 10 days after the
     * most recent rejection. Null once approved/resubmitted, or if
     * rejected_at somehow was never stamped.
     */
    public function rejectionDeadline(): ?\Illuminate\Support\Carbon
    {
        if (! $this->isRejectedPendingResubmission() || ! $this->informationSheet->rejected_at) {
            return null;
        }

        return $this->informationSheet->rejected_at->copy()->addDays(10);
    }

    /**
     * Everywhere the Information Sheet itself is locked for editing (see
     * InformationSheetController::update()'s two abort_if calls): either
     * it's Approved for good, or today happens to be the scheduled
     * evaluation day. Anything else that writes into the same underlying
     * records (StartupProfileController's Business Description sync, Core
     * Team CRUD, and InformationSheetController::update()'s own one-way
     * push of business_description/mobile_no/residential_address into the
     * Profile's business_description/contact_phone/location) needs to
     * respect this exact same window, or it becomes a back door around the
     * lock.
     */
    public function isInformationSheetLocked(): bool
    {
        return $this->hasApprovedInformationSheet() || $this->evaluationDayLockActive();
    }

    /**
     * True once a startup has completed Profile Setup (industry, location,
     * phone, photo) and the Information Sheet (business description +
     * actually submitted, not just saved), AND has a non-cancelled
     * evaluation scheduled. Backs both the 'Pending' branch of the status
     * accessor above and scopeAwaitingEvaluation() below — kept as one
     * source of truth so the card badge and the tab filter never disagree.
     */
    protected function isReadyForEvaluation(): bool
    {
        $sheet = $this->informationSheet;

        $profileComplete = $this->isProfileComplete()
            && $sheet
            && filled($sheet->business_description)
            && filled($sheet->submission_date);

        return $profileComplete && $this->hasScheduledEvaluation();
    }

    /**
     * The Assessment Hub's "Awaiting Schedule" list: a startup that has
     * actually submitted its Information Sheet, is still Pending, and has no
     * active booking.
     *
     * submission_date is the part that matters. A blank sheet row is created
     * the moment a founder saves their Startup Profile
     * (StartupProfileController::update()'s updateOrCreate), so a founder who
     * has never opened the Information Sheet used to sit in this list looking
     * ready to book. Only a real submission counts now.
     */
    public function scopeAwaitingSchedule(Builder $query): Builder
    {
        return $query
            ->whereHas('informationSheet', fn ($q) => $q
                ->where('approval_status', 'Pending')
                ->whereNotNull('submission_date'))
            ->whereDoesntHave('evaluationSchedules', fn ($q) => $q->where('status', 'Scheduled'));
    }

    /**
     * Broader "Pending" used by the Assessment Hub's "Awaiting Schedule"
     * list — any startup not yet approved/rejected, including ones with no
     * Information Sheet at all. Left as-is (not narrowed to match the
     * Startup Profile page's stricter 'Pending' tab, see
     * scopeAwaitingEvaluation()) since AssessmentHubController relies on
     * this exact broad definition and then filters scheduling separately.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereHas('informationSheet', fn ($q2) => $q2->where('approval_status', 'Pending'))
                ->orWhereDoesntHave('informationSheet');
        });
    }

    /**
     * "Onboarding" tab on the Startup Profile page — not yet approved or
     * rejected, and NOT (yet) ready for evaluation per isReadyForEvaluation()
     * above: still missing Profile Setup fields, hasn't submitted the
     * Information Sheet, or hasn't been scheduled for evaluation yet.
     */
    public function scopeOnboarding(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('informationSheet', fn ($q) => $q->whereIn('approval_status', ['Approved', 'Rejected']))
            ->where(function ($q) {
                $q->whereNull('industry_sector')->orWhere('industry_sector', '')
                    ->orWhereNull('location')->orWhere('location', '')
                    ->orWhereNull('contact_phone')->orWhere('contact_phone', '')
                    ->orWhereNull('startup_photo_path')->orWhere('startup_photo_path', '')
                    ->orWhereDoesntHave('informationSheet', fn ($q2) => $q2
                        ->whereNotNull('business_description')->where('business_description', '!=', '')
                        ->whereNotNull('submission_date'))
                    ->orWhereDoesntHave('evaluationSchedules', fn ($q2) => $q2->where('status', '!=', 'Cancelled'));
            });
    }

    /**
     * "Pending" tab on the Startup Profile page — the exact inverse of
     * scopeOnboarding() within the not-yet-decided pool: Profile Setup and
     * the Information Sheet are both complete, and an evaluation has been
     * scheduled. Distinct from the broader scopePending() above, which the
     * Assessment Hub still relies on.
     */
    public function scopeAwaitingEvaluation(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('informationSheet', fn ($q) => $q->whereIn('approval_status', ['Approved', 'Rejected']))
            ->whereNotNull('industry_sector')->where('industry_sector', '!=', '')
            ->whereNotNull('location')->where('location', '!=', '')
            ->whereNotNull('contact_phone')->where('contact_phone', '!=', '')
            ->whereNotNull('startup_photo_path')->where('startup_photo_path', '!=', '')
            ->whereHas('informationSheet', fn ($q) => $q
                ->whereNotNull('business_description')->where('business_description', '!=', '')
                ->whereNotNull('submission_date'))
            ->whereHas('evaluationSchedules', fn ($q) => $q->where('status', '!=', 'Cancelled'));
    }

    /**
     * Startups that have exited the program (Graduated or Completed — see
     * getExitStatusAttribute()) are excluded here: once a startup exits it
     * moves to the Graduated/Completed tab instead, the same way
     * WelcomeController already treats an exited startup as no longer
     * "active" on the public site.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('informationSheet', fn ($q) => $q->where('approval_status', 'Approved'))
            ->whereHas('activeCoordinatorAssignment')
            ->whereDoesntHave('ventureExitDocument', fn ($q) => $q->whereIn('data->exit_status', self::EXIT_STATUSES));
    }

    /**
     * Same exit exclusion as scopeActive() above — a startup that's already
     * Graduated/Completed doesn't belong on the "Assign Coordinator" tab
     * even if it happens to have no active assignment.
     */
    public function scopeNeedsCoordinator(Builder $query): Builder
    {
        return $query->whereHas('informationSheet', fn ($q) => $q->where('approval_status', 'Approved'))
            ->whereDoesntHave('activeCoordinatorAssignment')
            ->whereDoesntHave('ventureExitDocument', fn ($q) => $q->whereIn('data->exit_status', self::EXIT_STATUSES));
    }

    /**
     * "Graduated" tab/summary card on the Startup Profile page — the
     * Venture Exit form's Exit Status is specifically 'Graduated'. See
     * getExitStatusAttribute() for what counts.
     */
    public function scopeGraduated(Builder $query): Builder
    {
        return $query->whereHas('ventureExitDocument', fn ($q) => $q->where('data->exit_status', 'Graduated'));
    }

    /**
     * "Completed" tab/summary card on the Startup Profile page — the
     * Venture Exit form's Exit Status is specifically 'Completed'. See
     * getExitStatusAttribute() for what counts.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereHas('ventureExitDocument', fn ($q) => $q->where('data->exit_status', 'Completed'));
    }

    /**
     * Gates the admin Startup Profile page (and everywhere else a startup
     * needs to have a real, activated founder account behind it) — keys
     * off the founder's account_status (users.account_status), which is
     * distinct from the informationSheet approval_status used by the
     * scopes above. Verifying an email auto-activates the account (see
     * VerifyEmailController), so in practice this is equivalent to "has
     * verified their email" — the admin Founder Registrations screen
     * filters on email_verified_at directly instead, since that's the
     * literal concept it displays.
     */
    public function scopeApplicationApproved(Builder $query): Builder
    {
        return $query->whereHas('user', fn ($q) => $q->where('account_status', 'Active'));
    }
}