{{--
    Live "n / 1000 characters" counter for a Notes textarea, plus a hard stop
    at the limit. Place it IMMEDIATELY after the <textarea> (it finds the
    textarea as its previous sibling) inside an Alpine scope where $model
    (default `notes`) is the textarea's x-model.

    The limit mirrors the server rule ('notes' => max:1000 in the
    Store/Update EvaluationSchedule, AssessmentMeeting and AssignRoadblock
    requests), so the counter and the validation message can't disagree.
    Length is counted the way the server sees it: one per character (emoji
    included), but a line break counts as TWO — browsers submit textarea
    newlines as CRLF, and PHP's max:1000 counts both characters — otherwise a
    note showing 990/1000 with a few line breaks could still be rejected.
    Typing/pasting past the limit is trimmed at the limit. Grey normally,
    amber from 90%, red at the limit.
--}}
@props(['model' => 'notes', 'max' => 1000])

<div class="mt-1 flex justify-end text-xs"
    x-data="{ count(t) { let n = 0; for (const ch of (t || '')) n += ch === '\n' ? 2 : 1; return n; } }"
    x-init="const ta = $el.previousElementSibling;
        if (ta && ta.tagName === 'TEXTAREA') {
            ta.addEventListener('input', () => {
                let used = 0, kept = '';
                for (const ch of ta.value) { used += ch === '\n' ? 2 : 1; if (used > {{ $max }}) break; kept += ch; }
                if (kept !== ta.value) { ta.value = kept; ta.dispatchEvent(new Event('input', { bubbles: true })); }
            });
        }">
    <span :class="count({{ $model }}) >= {{ $max }} ? 'font-semibold text-red-600' : (count({{ $model }}) >= {{ (int) ($max * 0.9) }} ? 'text-amber-600' : 'text-gray-400')"
        x-text="count({{ $model }}) + ' / {{ $max }} characters'"></span>
</div>
