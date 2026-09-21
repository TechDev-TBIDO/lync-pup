<?php

namespace App\Notifications;

/**
 * Sent when an admin rejects the Information Sheet on evaluation day. The
 * founder can still revise and resubmit it — that resubmission then needs a
 * fresh evaluation before it can be decided again, see
 * Startup::evaluationReached(). $deadline (rejected_at + 10 days, see
 * Startup::rejectionDeadline()) is surfaced here too, since founders:
 * purge-expired-rejections removes the account automatically if nothing is
 * resubmitted by then.
 */
class InformationSheetRejected extends FounderNotification
{
    public function __construct(
        private readonly ?string $remarks = null,
        private readonly ?\Illuminate\Support\Carbon $deadline = null,
    ) {
    }

    public function title(): string
    {
        return 'Information Sheet not accepted';
    }

    public function body(): string
    {
        $deadlineNote = $this->deadline
            ? " Resubmit by {$this->deadline->format('F j, Y')} — accounts not resubmitted by then are automatically removed."
            : '';

        $remarks = $this->sentenceRemarks();

        return $remarks !== ''
            ? "TBIDO Form No.001 was not accepted this evaluation: {$remarks} You may revise and resubmit it.{$deadlineNote}"
            : "TBIDO Form No.001 was not accepted this evaluation. You may revise and resubmit it.{$deadlineNote}";
    }

    /**
     * The admin's remarks are free text — nothing makes them end in
     * punctuation, and they used to be spliced straight into the middle of
     * this card's message, so "Missing signature on page 2" ran into "You may
     * revise and resubmit it." with no break ("...page 2 You may revise...").
     * Normalised here so the card always reads as proper sentences: outer
     * whitespace trimmed, line breaks folded to single spaces (the card is one
     * paragraph), and a period added unless it already ends a sentence. The
     * rejection email shows the remarks as their own paragraph, so it doesn't
     * need this.
     */
    private function sentenceRemarks(): string
    {
        $remarks = trim((string) preg_replace('/\s+/u', ' ', (string) $this->remarks));

        if ($remarks === '') {
            return '';
        }

        return preg_match('/[.!?…]["\'”’)\]]*$/u', $remarks) ? $remarks : $remarks.'.';
    }

    public function route(): string
    {
        return 'startup.information-sheet.edit';
    }

    public function action(): string
    {
        return 'Revise Sheet';
    }

    public function icon(): string
    {
        return 'person-x.svg';
    }
}
