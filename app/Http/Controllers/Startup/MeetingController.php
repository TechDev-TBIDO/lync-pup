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

        $evaluations = EvaluationSchedule::where('startup_id', $startup->startup_id)
            ->where('status', 'Scheduled')
            ->get()
            ->reject->isMissed()
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
                ];
            });

        $assessmentMeetings = AssessmentMeeting::where('startup_id', $startup->startup_id)
            ->get()
            // Same day-based rule as the mentorship/evaluation rows above —
            // once a meeting's date has passed with nothing done about it,
            // it's the admin's Meetings > Archive tab's concern (reschedule
            // or delete), not something still worth showing the founder.
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

        return view('startup.meetings.index', compact('meetings'));
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
