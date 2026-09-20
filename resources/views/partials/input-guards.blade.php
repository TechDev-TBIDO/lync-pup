{{--
    Keystroke-level guards for the two input shapes every form shares. Opt an
    input in with an attribute — no per-page JS needed:

      data-person-name   letters, spaces and / - ' . ,  (names AND positions)
      data-ph-mobile     09XXXXXXXXX or +639XXXXXXXXX (digits, optional leading +)

    The listener runs in the CAPTURE phase so it cleans the value before
    Alpine's x-model (or any other handler) reads it. The server enforces the
    same rules (App\Rules\PersonName / App\Rules\PhMobile) — this only stops
    bad characters from landing in the box in the first place.
--}}
<script>
    (function () {
        if (window.__inputGuards) return;
        window.__inputGuards = true;

        // Whole-form checks (used by the assessment forms' submit handlers so a
        // wrong name / contact number blocks the save instead of only being
        // flagged). Same rules as App\Rules\PersonName / App\Rules\PhMobile.
        var NAME_OK = /^(?=.*\p{L})[\p{L}\p{M}\s\/\-'’.,]+$/u;
        var PHONE_OK = /^(09\d{9}|\+639\d{9})$/;
        window.LyncFormat = {
            nameOk: function (v) { v = String(v == null ? '' : v).trim(); return v === '' || NAME_OK.test(v); },
            phoneOk: function (v) { v = String(v == null ? '' : v).trim(); return v === '' || PHONE_OK.test(v); },
            // list = [[label, value, 'name' | 'phone'], ...] -> first problem as a sentence, or null.
            firstProblem: function (list) {
                for (var i = 0; i < list.length; i++) {
                    var label = list[i][0], value = list[i][1], kind = list[i][2];
                    if (kind === 'name' && !this.nameOk(value)) {
                        return label + " may only contain letters and / - ' . , (no numbers or other symbols).";
                    }
                    if (kind === 'phone' && !this.phoneOk(value)) {
                        return label + ' must use the format 09XXXXXXXXX or +639XXXXXXXXX.';
                    }
                }
                return null;
            },
        };

        var NAME_BAD = /[^\p{L}\p{M}\s\/\-'’.,]/gu;

        function cleanMobile(raw) {
            var hasPlus = raw.charAt(0) === '+';
            var digits = raw.replace(/[^0-9]/g, '');
            return (hasPlus ? '+' : '') + digits.slice(0, hasPlus ? 12 : 11);
        }

        document.addEventListener('input', function (e) {
            var el = e.target;
            if (!el || !el.hasAttribute) return;

            var before = el.value;
            var after = before;

            if (el.hasAttribute('data-person-name')) {
                after = before.replace(NAME_BAD, '');
            } else if (el.hasAttribute('data-ph-mobile')) {
                after = cleanMobile(before);
            } else {
                return;
            }

            if (after !== before) {
                var pos = el.selectionStart;
                el.value = after;
                try {
                    var p = Math.max(0, (pos || after.length) - (before.length - after.length));
                    el.setSelectionRange(p, p);
                } catch (err) { /* not all input types support selection */ }
            }
        }, true);
    })();
</script>
