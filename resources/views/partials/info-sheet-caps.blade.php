{{--
    Information Sheet: everything typed is in CAPITAL LETTERS, and so is every
    placeholder. Scoped to the sheet's root (.info-sheet-caps) so the rest of the
    app is untouched.

    - CSS shows existing values and all placeholders in caps.
    - JS converts what is typed/pasted to caps before Alpine (x-model) or the
      form sees it, so the saved value is uppercase too, not just the display.

    Left alone: email addresses (upper-casing an address can break delivery on
    case-sensitive mail servers), dates/numbers/files, and anything marked
    data-no-caps (e.g. the admin's rejection remarks to the founder).
--}}
<style>
    .info-sheet-caps input:not([type=email]):not([type=date]):not([type=number]):not([type=file]):not([type=password]):not([name*=email]):not([data-no-caps]),
    .info-sheet-caps textarea:not([name*=email]):not([data-no-caps]),
    .info-sheet-caps select:not([data-no-caps]) {
        text-transform: uppercase !important;
    }
    .info-sheet-caps input:not([type=email]):not([name*=email]):not([data-no-caps])::placeholder,
    .info-sheet-caps textarea:not([name*=email]):not([data-no-caps])::placeholder {
        text-transform: uppercase !important;
    }
</style>
<script>
    (function () {
        if (window.__infoSheetCaps) return;
        window.__infoSheetCaps = true;

        const SKIP_TYPES = ['email', 'date', 'number', 'file', 'password', 'hidden', 'checkbox', 'radio', 'range', 'color', 'url'];

        function wantsCaps(el) {
            if (!el || !el.closest || !el.closest('.info-sheet-caps')) return false;
            if (el.tagName === 'TEXTAREA') {
                // fall through to the name/data checks below
            } else if (el.tagName === 'INPUT') {
                if (SKIP_TYPES.includes((el.type || 'text').toLowerCase())) return false;
            } else {
                return false;
            }
            if (el.hasAttribute('data-no-caps')) return false;
            if ((el.name || '').toLowerCase().includes('email')) return false;
            return true;
        }

        // Capture phase on document runs before the field's own listeners
        // (x-model, input guards, autoGrow), so they all see the caps value.
        document.addEventListener('input', function (e) {
            const el = e.target;
            if (!wantsCaps(el) || el.readOnly) return;
            const upper = el.value.toLocaleUpperCase('en-US');
            if (upper === el.value) return;
            const start = el.selectionStart, end = el.selectionEnd;
            el.value = upper;
            try { el.setSelectionRange(start, end); } catch (_) {}
        }, true);

        // Values already on the sheet (saved before this change, or prefilled)
        // are converted when the form is submitted, so a save stores caps.
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const fields = Array.from(form.elements || []);
            if (form.id) {
                document.querySelectorAll('[form="' + form.id + '"]').forEach(function (f) {
                    if (!fields.includes(f)) fields.push(f);
                });
            }
            fields.forEach(function (el) {
                if (wantsCaps(el)) el.value = el.value.toLocaleUpperCase('en-US');
            });
        }, true);
    })();
</script>
