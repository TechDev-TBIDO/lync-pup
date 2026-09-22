<?php

namespace App\Http\Controllers\Startup;

use App\Http\Controllers\Controller;
use App\Models\AssessmentMeeting;
use App\Models\EvaluationSchedule;
use App\Models\Roadblock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function index(): View
    {
        Roadblock::promoteEndedMeetingsToPendingReview();
        AssessmentMeeting::promoteEndedMeetingsToPendingReview();

        $startup = Auth::user()->startup;

        $mentorships = Roadblock::with(['mentor', 'coordinator'])
            ->where('startup_id', $startup->startup_id)
            ->where('status', 'Scheduled')
            ->whereNotNull('meeting_date')
            ->get()
            ->reject->isInAssessment()
            ->map(function (Roadblock $roadblock) {
                return [
                    'type' => 'mentorship',
                    'sort_key' => $roadblock->meeting_date->format('Y-m-d') . ' ' . $roadblock->meeting_start_time,
                    'date_label' => $roadblock->meeting_date->format('l, F j, Y'),
                    'time_label' => Carbon::parse($roadblock->meeting_start_time)->format('g:i A')
                        . ' - ' . Carbon::parse($roadblock->meeting_end_time)->format('g:i A'),
                    'status_label' => $roadblock->meeting_status_label,
                    'roadblock_category' => $roadblock->display_category,
                    // Mentor and Coordinator are the same shape here (both expose
                    // honorific/last_name/display_name), so this works for either.
                    'mentor_name' => $roadblock->assignee?->display_name ?: '—',
                    'platform' => $roadblock->meeting_platform,
                    'meeting_link' => $roadblock->meeting_link,
                    'notes' => $roadblock->notes,
                    // Joinable for the whole meeting day, not just its exact
                    // start–end window — see Roadblock::isJoinable().
                    'can_join' => $roadblock->isJoinable(),
                ];
            });

        // Every EvaluationSchedule row ever created for this startup carries
        // status 'Scheduled' for as long as it exists (nothing ever flips it
        // to anything else — see EvaluationScheduleController), so the only
        // reliable "is this still live" signal is whether its own booked
        // time has passed yet (hasEnded()), not isMissed() alone: isMissed()
        // only catches a slot that ran out WITHOUT an approval, so a slot
        // that was approved (or rejected) within its own day used to stay on
        // this "active" list forever instead of moving to Archive once
        // Item 2 needed a real destination for it.
        $evaluations = EvaluationSchedule::where('startup_id', $startup->startup_id)
            ->where('status', 'Scheduled')
            ->get()
            ->reject(fn (EvaluationSchedule $schedule) => $schedule->hasEnded())
            ->map(function (EvaluationSchedule $schedule) {
                return [
                    'type' => 'evaluation',
                    'sort_key' => $schedule->evaluation_date->format('Y-m-d') . ' ' . $schedule->start_time,
                    'date_label' => $schedule->evaluation_date->format('l, F j, Y'),
                    'time_label' => $schedule->time_range_label,
                    'status_label' => $this->dayLabel($schedule->evaluation_date),
                    'platform' => $schedule->modality,
                    'meeting_link' => $schedule->link,
                    'notes' => $schedule->notes,
                    // Joinable for the whole evaluation day, same day-based
                    // rule as Roadblock::isJoinable() / the assessment row
                    // below — gates the founder Meeting page's Join button
                    // (only actually rendered when modality isn't 'Location').
                    'can_join' => $schedule->evaluation_date->isToday(),
                ];
            });

        $assessmentMeetingsAll = AssessmentMeeting::where('startup_id', $startup->startup_id)->get();

        $assessmentMeetings = $assessmentMeetingsAll
            // Same rule as the mentorship rows above: once a meeting's time
            // has passed (Pending Review) or the admin has closed it out
            // (Resolved/Failed) it moves to this page's own Archive tab
            // below instead of staying here.
            ->reject->isArchived()
            ->map(function (AssessmentMeeting $meeting) {
                return [
                    'type' => 'assessment',
                    'sort_key' => $meeting->meeting_date->format('Y-m-d').' '.$meeting->start_time,
                    'date_label' => $meeting->meeting_date->format('l, F j, Y'),
                    'time_label' => $meeting->time_range_label,
                    'status_label' => $this->dayLabel($meeting->meeting_date),
                    'stage_label' => $meeting->stage,
                    'platform' => $meeting->modality,
                    'meeting_link' => $meeting->link,
                    'notes' => $meeting->notes,
                    'can_join' => $meeting->meeting_date->isToday(),
                ];
            });

        $meetings = $mentorships->concat($evaluations)->concat($assessmentMeetings)->sortBy('sort_key')->values();

        $archivedMeetings = $this->archivedMentorships($startup->startup_id)
            ->concat($this->archivedEvaluations($startup->startup_id))
            ->concat($this->archivedAssessmentMeetings($assessmentMeetingsAll))
            // Most recently archived first — the opposite ordering from the
            // active list above, which reads soonest-first.
            ->sortByDesc('sort_key')
            ->values();

        return view('startup.meetings.index', compact('meetings', 'archivedMeetings'));
    }

    /**
     * Roadblock/mentorship meetings that have left the active list — the
     * meeting's own scheduled time has passed and/or the admin has closed it
     * out. Tagged with the roadblock's actual status, the exact same wording
     * already shown on the founder's own Roadblock page's Archive tab
     * (Pending Review / Resolved / Failed / Deleted by Admin) — see
     * Roadblock page's $archiveStatuses / $statusColors.
     *
     * A roadblock that was never assigned a mentor/coordinator (still
     * 'Pending', no meeting_date) never had a meeting to begin with, so it
     * has no place on this Meetings page at all, active or archived — that
     * one stays exclusively on the Roadblock page's own Archive tab.
     *
     * Roadblock::promoteEndedMeetingsToPendingReview() already ran at the
     * top of index(), so any 'Scheduled' row whose meeting has ended is
     * already 'Pending Review' by the time this query runs — no row here
     * needs its own hasEnded() check the way evaluations below do.
     */
    private function archivedMentorships(string $startupId)
    {
        return Roadblock::with(['mentor', 'coordinator'])
            ->where('startup_id', $startupId)
            ->whereNotNull('meeting_date')
            ->whereIn('status', ['Pending Review', 'Resolved', 'Failed', 'Deleted by Admin'])
            ->get()
            ->map(function (Roadblock $roadblock) {
                return [
                    'type' => 'mentorship',
                    'sort_key' => $roadblock->meeting_date->format('Y-m-d').' '.$roadblock->meeting_start_time,
                    'date_label' => $roadblock->meeting_date->format('l, F j, Y'),
                    'time_label' => $roadblock->meeting_time_range_label,
                    'roadblock_category' => $roadblock->display_category,
                    'mentor_name' => $roadblock->assignee_display_name ?: '—',
                    'archive_status' => $roadblock->status,
                ];
            });
    }

    /**
     * Information Sheet evaluation meetings whose booked slot has ended —
     * tagged Approved, Rejected, or Missed per EvaluationSchedule::archiveStatus(),
     * based on the sheet's decision (or lack of one) by the time the
     * evaluation day ended.
     */
    private function archivedEvaluations(string $startupId)
    {
        return EvaluationSchedule::where('startup_id', $startupId)
            ->where('status', 'Scheduled')
            ->get()
            ->filter(fn (EvaluationSchedule $schedule) => $schedule->hasEnded())
            ->map(function (EvaluationSchedule $schedule) {
                return [
                    'type' => 'evaluation',
                    'sort_key' => $schedule->evaluation_date->format('Y-m-d').' '.$schedule->start_time,
                    'date_label' => $schedule->evaluation_date->format('l, F j, Y'),
                    'time_label' => $schedule->time_range_label,
                    'archive_status' => $schedule->archiveStatus(),
                ];
            });
    }

    /**
     * Assessment Hub / RL document meetings whose time has passed or that
     * the admin has already closed out — tagged with AssessmentMeeting's own
     * status (Pending Review / Resolved / Failed), the same three labels a
     * founder already sees for their roadblock meetings above, per Item 1.
     *
     * Takes the already-loaded collection from index() rather than
     * re-querying, since the active list above needs the exact same rows.
     */
    private function archivedAssessmentMeetings($assessmentMeetingsAll)
    {
        return $assessmentMeetingsAll
            ->filter->isArchived()
            ->map(function (AssessmentMeeting $meeting) {
                return [
                    'type' => 'assessment',
                    'sort_key' => $meeting->meeting_date->format('Y-m-d').' '.$meeting->start_time,
                    'date_label' => $meeting->meeting_date->format('l, F j, Y'),
                    'time_label' => $meeting->time_range_label,
                    'stage_label' => $meeting->stage,
                    'archive_status' => $meeting->status,
                ];
            });
    }

    /**
     * Evaluation schedules only — EvaluationSchedule has no equivalent of
     * Roadblock::meeting_status_label, so this coarser Today/Tomorrow/
     * Upcoming label is still used for the $evaluations row above.
     */
    private function dayLabel(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isTomorrow()) {
            return 'Tomorrow';
        }

        return 'Upcoming';
    }
}
