<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEvaluationScheduleRequest;
use App\Http\Requests\Admin\UpdateEvaluationScheduleRequest;
use App\Models\EvaluationSchedule;
use App\Models\VersionHistory;
use App\Notifications\EvaluationCancelled;
use App\Notifications\EvaluationScheduled;
use App\Support\ChangeLog;
use App\Support\HistoryFields;
use Illuminate\Http\RedirectResponse;

class EvaluationScheduleController extends Controller
{
    public function store(StoreEvaluationScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['end_time'] = $this->endTimeFor($data['start_time']);
        $data['status'] = 'Scheduled';

        $evaluationSchedule = EvaluationSchedule::create($data);

        if ($evaluationSchedule->startup) {
            VersionHistory::record(
                $evaluationSchedule->startup,
                'Information Sheet',
                'set_evaluation',
                changes: ChangeLog::initial($evaluationSchedule, HistoryFields::evaluationSchedule()),
            );
        }

        $this->notifyFounder($evaluationSchedule, rescheduled: false);

        return back()->with('status', 'Evaluation scheduled successfully.');
    }

    public function update(UpdateEvaluationScheduleRequest $request, EvaluationSchedule $evaluationSchedule): RedirectResponse
    {
        $data = $request->validated();
        $data['end_time'] = $this->endTimeFor($data['start_time']);
        $data['status'] = 'Scheduled';

        // What the reschedule really moved (date, time, modality, ...) — one
        // that changes nothing isn't logged.
        $changes = ChangeLog::track($evaluationSchedule, HistoryFields::evaluationSchedule(), fn () => $evaluationSchedule->update($data));

        if ($evaluationSchedule->startup) {
            VersionHistory::recordChanges($evaluationSchedule->startup, 'Information Sheet', 'reschedule_evaluation', $changes);
        }

        $this->notifyFounder($evaluationSchedule, rescheduled: true);

        return back()->with('status', 'Evaluation schedule saved successfully.');
    }

    public function destroy(EvaluationSchedule $evaluationSchedule): RedirectResponse
    {
        // Decided before the row goes: only a booking the founder could still
        // see on their Meetings page (Scheduled and not already missed —
        // same filter as Startup\MeetingController) is worth announcing as
        // cancelled. A stale/missed one was already hidden from them.
        $wasVisibleToFounder = $evaluationSchedule->status === 'Scheduled'
            && ! $evaluationSchedule->isMissed();

        $startup = $evaluationSchedule->startup;

        $evaluationSchedule->delete();

        if ($startup) {
            // Says which booking went, since the row itself is gone.
            $slot = collect([
                $evaluationSchedule->evaluation_date?->format('M j, Y'),
                $evaluationSchedule->start_time ? \Illuminate\Support\Carbon::parse($evaluationSchedule->start_time)->format('g:i A') : null,
            ])->filter()->implode(', ');

            VersionHistory::record(
                $startup,
                'Information Sheet',
                'delete_evaluation',
                changes: $slot === '' ? [] : ChangeLog::note("Evaluation removed ({$slot})"),
            );
        }

        // Always take down a still-unread "Evaluation scheduled" card for
        // this booking, so it can't keep pointing the founder at a slot that
        // no longer exists — and, when they could see it, tell them why it's
        // gone.
        if ($user = $startup?->user) {
            $user->unreadNotifications()
                ->where('type', EvaluationScheduled::class)
                ->where('data->evaluation_schedule_id', $evaluationSchedule->evaluation_schedule_id)
                ->delete();

            if ($wasVisibleToFounder) {
                $user->notify(new EvaluationCancelled($evaluationSchedule));
            }
        }

        return back()->with('status', 'Evaluation schedule deleted.');
    }

    private function endTimeFor(string $startTime): string
    {
        foreach (EvaluationSchedule::TIME_SLOTS as [$start, $end]) {
            if ($start === $startTime) {
                return $end;
            }
        }

        abort(422, 'Invalid time slot.');
    }

    /**
     * A reschedule refreshes the founder's still-unread card for this same
     * booking in place (the title flips to "rescheduled"); once they've read
     * it, a fresh card goes out instead so the change isn't missed.
     */
    private function notifyFounder(EvaluationSchedule $schedule, bool $rescheduled): void
    {
        $user = $schedule->startup?->user;

        if (! $user) {
            return;
        }

        $notification = new EvaluationScheduled($schedule, $rescheduled);

        $existing = $user->unreadNotifications()
            ->where('type', EvaluationScheduled::class)
            ->where('data->evaluation_schedule_id', $schedule->evaluation_schedule_id)
            ->first();

        if ($existing) {
            $existing->forceFill(['data' => $notification->toDatabase($user)])->save();
        } else {
            $user->notify($notification);
        }
    }
}
