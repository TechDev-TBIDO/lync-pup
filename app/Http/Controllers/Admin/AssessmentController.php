<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentDocument;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Models\VersionHistory;
use App\Notifications\ReadinessResultsReleased;
use App\Notifications\WeeklyCheckInPosted;
use App\Rules\PersonName;
use App\Rules\PhMobile;
use App\Support\ChangeLog;
use App\Support\HistoryFields;
use App\Support\ReadinessRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    /**
     * Saves one stage's worth of TRL/MRL/TMRL/SRL checklist progress for a
     * startup. The form posts each RL type's progress as a JSON string
     * (serialized client-side from Alpine state) rather than a nested
     * array, since the checklists are keyed by level number with a
     * variable-length boolean array per level — awkward to express as
     * conventional bracketed form field names, but trivial as JSON.
     */
    public function update(Request $request, Startup $startup): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:'.implode(',', ReadinessRubric::STAGES)],
            'trl_progress' => ['nullable', 'json'],
            'mrl_progress' => ['nullable', 'json'],
            'tmrl_progress' => ['nullable', 'json'],
            'srl_progress' => ['nullable', 'json'],
            // Only ever submitted from the Pre-Assessment TRL tab (Section 1:
            // Startup & Technology Overview) — absent everywhere else.
            'trl_overview' => ['nullable', 'json'],
            // Editable Date of Assessment picker — also only present on that
            // same TRL Pre-Assessment tab; falls back to today when absent.
            'assessment_date' => ['nullable', 'date'],
            // TRL's own signatory block ("Prepared By" / "Noted By" /
            // "Approved by") — distinct from the MRL/TMRL blocks below.
            // "Approved by" is editable but arrives pre-filled with the
            // director's fixed signature, so it's stored like its siblings.
            'prepared_by' => ['nullable', 'string', 'max:150', new PersonName],
            'prepared_by_position' => ['nullable', 'string', 'max:150', new PersonName],
            'trl_noted_by' => ['nullable', 'string', 'max:150', new PersonName],
            'trl_noted_by_position' => ['nullable', 'string', 'max:150', new PersonName],
            'approved_by' => ['nullable', 'string', 'max:150', new PersonName],
            'approved_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
            // Editable captions for the three TRL signatories.
            'prepared_by_label' => ['nullable', 'string', 'max:60'],
            'trl_noted_by_label' => ['nullable', 'string', 'max:60'],
            'approved_by_label' => ['nullable', 'string', 'max:60'],
            'mrl_evaluated_by_label' => ['nullable', 'string', 'max:60'],
            'mrl_reviewed_by_label' => ['nullable', 'string', 'max:60'],
            'mrl_noted_by_label' => ['nullable', 'string', 'max:60'],
            'tmrl_evaluated_by_label' => ['nullable', 'string', 'max:60'],
            'tmrl_reviewed_by_label' => ['nullable', 'string', 'max:60'],
            'tmrl_noted_by_label' => ['nullable', 'string', 'max:60'],
            'srl_evaluated_by_label' => ['nullable', 'string', 'max:60'],
            'srl_reviewed_by_label' => ['nullable', 'string', 'max:60'],
            'srl_noted_by_label' => ['nullable', 'string', 'max:60'],
            // MRL and TMRL's own independent Evaluated/Reviewed/Noted by
            // blocks — used to be one shared set of columns (see the
            // migration that split them), which meant editing MRL's block
            // silently overwrote TMRL's (and vice versa) since both tabs
            // wrote to the exact same fields. Submitted once per save
            // regardless of which tab is active, same as every other RL
            // type's signatory block.
            'mrl_evaluated_by' => ['nullable', 'string', 'max:150', new PersonName],
            'mrl_evaluated_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
            'mrl_reviewed_by' => ['nullable', 'string', 'max:150', new PersonName],
            'mrl_reviewed_by_position' => ['nullable', 'string', 'max:150', new PersonName],
            'mrl_noted_by' => ['nullable', 'string', 'max:150', new PersonName],
            'mrl_noted_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
            'tmrl_evaluated_by' => ['nullable', 'string', 'max:150', new PersonName],
            'tmrl_evaluated_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
            'tmrl_reviewed_by' => ['nullable', 'string', 'max:150', new PersonName],
            'tmrl_reviewed_by_position' => ['nullable', 'string', 'max:150', new PersonName],
            'tmrl_noted_by' => ['nullable', 'string', 'max:150', new PersonName],
            'tmrl_noted_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
            // SRL's own Evaluated/Reviewed/Noted by block — distinct
            // columns from the MRL/TMRL block above (different default
            // "Reviewed by" title, so it can't share the same fields).
            'srl_evaluated_by' => ['nullable', 'string', 'max:150', new PersonName],
            'srl_evaluated_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
            'srl_reviewed_by' => ['nullable', 'string', 'max:150', new PersonName],
            'srl_reviewed_by_position' => ['nullable', 'string', 'max:150', new PersonName],
            'srl_noted_by' => ['nullable', 'string', 'max:150', new PersonName],
            'srl_noted_by_position' => ['nullable', 'string', 'max:1000', new PersonName],
        ]);

        // The overview arrives as one JSON blob, so its contact number can't be
        // covered by a per-field rule above. A malformed number rejects the
        // whole save - nothing below runs.
        if (isset($validated['trl_overview'])) {
            $this->assertFieldFormats('TRL Overview', json_decode($validated['trl_overview'], true), 'trl_overview');
        }

        // A filled-in signatory label makes that signatory's name and position
        // required (mirrors LyncFormat.signatoryCheck on the page).
        $signatoryPrefixes = ['TRL' => ['prepared_by', 'trl_noted_by', 'approved_by']];
        foreach (['mrl' => 'MRL', 'tmrl' => 'TMRL', 'srl' => 'SRL'] as $key => $type) {
            $signatoryPrefixes[$type] = ["{$key}_evaluated_by", "{$key}_reviewed_by", "{$key}_noted_by"];
        }
        // No label = no signatory: its name and position are not kept.
        foreach ($signatoryPrefixes as $prefixes) {
            foreach ($prefixes as $prefix) {
                if (trim((string) ($validated["{$prefix}_label"] ?? '')) === '') {
                    $validated[$prefix] = null;
                    $validated["{$prefix}_position"] = null;
                }
            }
        }

        $signatoryErrors = [];
        foreach ($signatoryPrefixes as $type => $prefixes) {
            foreach ($prefixes as $prefix) {
                $signatoryErrors = [...$signatoryErrors, ...$this->signatoryErrors(
                    $type,
                    $validated["{$prefix}_label"] ?? null,
                    [[$validated[$prefix] ?? null, $validated["{$prefix}_position"] ?? null]],
                )];
            }
        }
        if ($signatoryErrors !== []) {
            throw ValidationException::withMessages(['signatories' => $signatoryErrors]);
        }

        $assessment = ReadinessLevelAssessment::firstOrNew([
            'startup_id' => $startup->startup_id,
            'stage' => $validated['stage'],
        ]);

        // Everything about to be overwritten, read now so the Edit History
        // entry can say what this save changed: the plain columns (date,
        // signatories, scores), each RL type's ticked criteria, and the TRL
        // overview.
        $historyFields = HistoryFields::readinessAssessment();
        $columnsBefore = ChangeLog::snapshot($assessment, $historyFields);
        $progressBefore = collect(ReadinessRubric::TYPES)
            ->mapWithKeys(fn ($type) => [$type => $assessment->progressFor($type)])
            ->all();
        $overviewBefore = $assessment->trl_overview;

        foreach (ReadinessRubric::TYPES as $type) {
            $key = strtolower($type).'_progress';
            $assessment->{$key} = isset($validated[$key]) ? json_decode($validated[$key], true) : [];
        }

        if (isset($validated['trl_overview'])) {
            $assessment->trl_overview = json_decode($validated['trl_overview'], true);
        }

        $assessment->prepared_by = $validated['prepared_by'] ?? null;
        $assessment->prepared_by_position = $validated['prepared_by_position'] ?? null;
        $assessment->trl_noted_by = $validated['trl_noted_by'] ?? null;
        $assessment->trl_noted_by_position = $validated['trl_noted_by_position'] ?? null;
        $assessment->approved_by = $validated['approved_by'] ?? null;
        $assessment->approved_by_position = $validated['approved_by_position'] ?? null;
        $assessment->prepared_by_label = $validated['prepared_by_label'] ?? null;
        $assessment->trl_noted_by_label = $validated['trl_noted_by_label'] ?? null;
        $assessment->approved_by_label = $validated['approved_by_label'] ?? null;
        // No signatory is auto-filled: a blank field saves as blank.
        $assessment->mrl_evaluated_by = $validated['mrl_evaluated_by'] ?? null;
        $assessment->mrl_evaluated_by_position = $validated['mrl_evaluated_by_position'] ?? null;
        $assessment->mrl_reviewed_by = $validated['mrl_reviewed_by'] ?? null;
        $assessment->mrl_reviewed_by_position = $validated['mrl_reviewed_by_position'] ?? null;
        $assessment->mrl_noted_by = $validated['mrl_noted_by'] ?? null;
        $assessment->mrl_noted_by_position = $validated['mrl_noted_by_position'] ?? null;
        $assessment->tmrl_evaluated_by = $validated['tmrl_evaluated_by'] ?? null;
        $assessment->tmrl_evaluated_by_position = $validated['tmrl_evaluated_by_position'] ?? null;
        $assessment->tmrl_reviewed_by = $validated['tmrl_reviewed_by'] ?? null;
        $assessment->tmrl_reviewed_by_position = $validated['tmrl_reviewed_by_position'] ?? null;
        $assessment->tmrl_noted_by = $validated['tmrl_noted_by'] ?? null;
        $assessment->tmrl_noted_by_position = $validated['tmrl_noted_by_position'] ?? null;
        $assessment->srl_evaluated_by = $validated['srl_evaluated_by'] ?? null;
        $assessment->srl_evaluated_by_position = $validated['srl_evaluated_by_position'] ?? null;
        $assessment->srl_reviewed_by = $validated['srl_reviewed_by'] ?? null;
        $assessment->srl_reviewed_by_position = $validated['srl_reviewed_by_position'] ?? null;
        $assessment->srl_noted_by = $validated['srl_noted_by'] ?? null;
        $assessment->srl_noted_by_position = $validated['srl_noted_by_position'] ?? null;
        $assessment->mrl_evaluated_by_label = $validated['mrl_evaluated_by_label'] ?? null;
        $assessment->mrl_reviewed_by_label = $validated['mrl_reviewed_by_label'] ?? null;
        $assessment->mrl_noted_by_label = $validated['mrl_noted_by_label'] ?? null;
        $assessment->tmrl_evaluated_by_label = $validated['tmrl_evaluated_by_label'] ?? null;
        $assessment->tmrl_reviewed_by_label = $validated['tmrl_reviewed_by_label'] ?? null;
        $assessment->tmrl_noted_by_label = $validated['tmrl_noted_by_label'] ?? null;
        $assessment->srl_evaluated_by_label = $validated['srl_evaluated_by_label'] ?? null;
        $assessment->srl_reviewed_by_label = $validated['srl_reviewed_by_label'] ?? null;
        $assessment->srl_noted_by_label = $validated['srl_noted_by_label'] ?? null;
        $assessment->assessment_date = $validated['assessment_date'] ?? now();

        // Captured before recomputeScores() so the notification below fires
        // exactly once — on the save that first produces a score. The admin
        // saves this form repeatedly while ticking through the checklists, and
        // notifying on every save would bury the founder's dashboard in duplicates.
        $wasScored = $assessment->exists && $assessment->overall_score !== null;

        $assessment->recomputeScores();
        $assessment->save();

        if (! $wasScored && $assessment->overall_score !== null) {
            $startup->user?->notify(new ReadinessResultsReleased($validated['stage']));
        }

        $changes = [];

        foreach (ReadinessRubric::TYPES as $type) {
            $changes = [...$changes, ...ChangeLog::diffProgress(
                $type,
                $progressBefore[$type],
                $assessment->progressFor($type),
                ReadinessRubric::levels($type),
            )];
        }

        $changes = [
            ...$changes,
            ...ChangeLog::diffTree(
                is_array($overviewBefore) ? $overviewBefore : null,
                is_array($assessment->trl_overview) ? $assessment->trl_overview : null,
                fn (array $path) => HistoryFields::overviewLabel($path),
            ),
            ...ChangeLog::diff($columnsBefore, ChangeLog::snapshot($assessment, $historyFields), $historyFields),
        ];

        // A save that changed nothing isn't logged.
        VersionHistory::recordChanges($startup, $validated['stage'], 'update_readiness_assessment', $changes);

        // Redirect back to the exact same RL type sub-tab the admin was on
        // (not just the same stage) — plain back() would land on the right
        // URL too, but a fresh page load still resets Alpine's activeType
        // to its default unless it's carried forward as a query param here.
        return redirect()->route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => $validated['stage'],
            'assessment_startup' => $startup->startup_id,
            'rl_type' => $request->input('active_type'),
        ])->with('status', 'Assessment saved successfully.');
    }

    /**
     * Saves a stage's free-form "document" forms for a startup — Document
     * 6/7/8 under Active-Assessment, and the Startup Exit Form (document 13)
     * under Venture Exit. Each document's full set of field values is
     * posted as one JSON string (serialized client-side from Alpine state),
     * same approach as update() above — these documents mix free text,
     * checkbox grids, and variable-length repeating tables, which doesn't
     * map cleanly onto plain bracketed form field names.
     */
    public function updateDocuments(Request $request, Startup $startup): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:'.implode(',', ReadinessRubric::STAGES)],
            'document_6' => ['nullable', 'json'],
            'document_7' => ['nullable', 'json'],
            'document_8' => ['nullable', 'json'],
            'document_13' => ['nullable', 'json'],
        ]);

        // Active-Assessment's Documents 6, 7 and 8 are three separate forms that
        // share one Save button: only the document whose tab is open is checked
        // and stored. (The form already posts just that one; this also guards
        // an older page that still posts all three, where a problem on another
        // tab used to reject the save of the open one.)
        if ($validated['stage'] === 'Active-Assessment' && in_array((int) $request->input('active_document'), [6, 7, 8], true)) {
            $activeKey = 'document_'.(int) $request->input('active_document');
            $validated = array_filter(
                $validated,
                fn ($key) => ! str_starts_with($key, 'document_') || $key === $activeKey,
                ARRAY_FILTER_USE_KEY,
            );
        }

        // Check every document's names/positions/contact numbers BEFORE any of
        // them is written - one bad field rejects the whole save instead of
        // leaving the other documents half-updated.
        foreach ([6, 7, 8, 13] as $documentNumber) {
            $key = 'document_'.$documentNumber;

            if (array_key_exists($key, $validated)) {
                $this->assertFieldFormats('Document '.$documentNumber, json_decode($validated[$key], true), $key);
            }
        }

        // A filled-in signatory label makes that signatory's name and position
        // required (mirrors LyncFormat.signatoryCheck on the page).
        $signatoryErrors = [];
        foreach ([6, 7, 8, 13] as $documentNumber) {
            $key = 'document_'.$documentNumber;
            if (array_key_exists($key, $validated)) {
                $signatoryErrors = [...$signatoryErrors, ...$this->documentSignatoryErrors(
                    $documentNumber,
                    (array) json_decode((string) $validated[$key], true),
                )];
            }
        }
        if ($signatoryErrors !== []) {
            throw ValidationException::withMessages(['signatories' => $signatoryErrors]);
        }

        $changes = [];

        foreach ([6, 7, 8, 13] as $documentNumber) {
            $key = 'document_'.$documentNumber;

            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $payload = $this->clearUnlabeledSignatories($documentNumber, json_decode($validated[$key], true));

            // What this document held before the save, so the Edit History
            // entry can list the fields that changed.
            $existing = AssessmentDocument::where('startup_id', $startup->startup_id)
                ->where('stage', $validated['stage'])
                ->where('document_number', $documentNumber)
                ->first();

            // Document 7 under Active-Assessment is the only one the founder
            // ever sees (it renders as the Weekly Update tab on their
            // Submission page), so it is the only one worth announcing.
            $isWeeklyCheckIns = $documentNumber === 7 && $validated['stage'] === 'Active-Assessment';
            $filledBefore = $isWeeklyCheckIns
                ? $this->filledCheckInCount($existing?->data)
                : 0;

            AssessmentDocument::updateOrCreate(
                [
                    'startup_id' => $startup->startup_id,
                    'stage' => $validated['stage'],
                    'document_number' => $documentNumber,
                ],
                ['data' => $payload]
            );

            $changes = [
                ...$changes,
                ...ChangeLog::prefixed(
                    ChangeLog::diffTree(
                        $existing?->data,
                        is_array($payload) ? $payload : null,
                        fn (array $path) => HistoryFields::documentLabel($documentNumber, $path),
                    ),
                    HistoryFields::DOCUMENT_NAMES[$documentNumber] ?? "Document {$documentNumber}",
                ),
            ];

            // Only a net-new check-in row is news. Editing a typo in an
            // existing row, or saving the document untouched from another tab,
            // leaves the count alone and stays silent.
            if ($isWeeklyCheckIns) {
                $added = $this->filledCheckInCount($payload) - $filledBefore;

                if ($added > 0) {
                    $startup->user?->notify(new WeeklyCheckInPosted($added));
                }
            }
        }

        // A save that changed nothing isn't logged.
        VersionHistory::recordChanges($startup, $validated['stage'], 'update_assessment_document', $changes);

        // Same as update() above — echo back which document sub-tab (6/7/8)
        // was open so Active-Assessment doesn't snap back to Document 6 on
        // reload. Venture Exit's single-document save has no sub-tab to
        // preserve, hence array_filter dropping the param when it's absent.
        return redirect()->route('admin.assessment-hub.index', array_filter([
            'main' => 'assessment',
            'stage' => $validated['stage'],
            'assessment_startup' => $startup->startup_id,
            'active_doc' => $validated['stage'] === 'Active-Assessment' ? $request->input('active_document') : null,
        ]))->with('status', 'Changes saved successfully.');
    }

    /**
     * Leaf keys inside the JSON documents that hold a person's name or job
     * title (Prepared/Noted/Approved/Validated/Evaluated/Reviewed by, plus the
     * plain `name` / `position` columns of Document 6's Prepared By rows).
     * Everything else in the payload is free text and left alone.
     */
    private const PERSON_KEY = '/^(name|position|noted_by|(prepared|noted|approved|validated|evaluated|reviewed)_by(_name|_position)?)$/';

    /**
     * Walks a decoded JSON document and rejects the save when a name/position
     * has anything but letters and / - ' . , (see PersonName), or a contact
     * number is not exactly 09XXXXXXXXX / +639XXXXXXXXX (see PhMobile).
     *
     * @throws ValidationException
     */
    /**
     * One signatory's "label filled => name and position required" rule.
     * $rows holds [name, position] pairs; several rows share one label
     * (Document 6's Prepared By), in which case every started row must be
     * complete and at least one row must be.
     *
     * @param  list<array{0: mixed, 1: mixed}>  $rows
     * @return list<string>
     */
    protected function signatoryErrors(string $where, mixed $label, array $rows): array
    {
        $label = trim((string) $label);
        if ($label === '') {
            return [];
        }

        $filled = fn ($value) => trim((string) $value) !== '';
        $complete = collect($rows)->filter(fn ($row) => $filled($row[0] ?? null) && $filled($row[1] ?? null));
        $partial = collect($rows)->filter(fn ($row) => $filled($row[0] ?? null) xor $filled($row[1] ?? null));

        if ($complete->isEmpty() || $partial->isNotEmpty()) {
            return ["{$where} - \"{$label}\" needs a name and position. Fill them in, or clear the label."];
        }

        return [];
    }

    /**
     * signatoryErrors() for each signatory of one Active-Assessment /
     * Venture Exit document's JSON payload.
     *
     * @return list<string>
     */
    protected function documentSignatoryErrors(int $documentNumber, array $data): array
    {
        $where = HistoryFields::DOCUMENT_NAMES[$documentNumber] ?? "Document {$documentNumber}";
        $one = fn (string $role, ?string $nameKey = null) => $this->signatoryErrors(
            $where,
            $data["{$role}_label"] ?? null,
            [[$data[$nameKey ?? "{$role}_name"] ?? null, $data["{$role}_position"] ?? null]],
        );

        return match ($documentNumber) {
            6 => [
                ...$this->signatoryErrors(
                    $where,
                    $data['prepared_by_label'] ?? null,
                    collect($data['prepared_by'] ?? [])->map(fn ($row) => [$row['name'] ?? null, $row['position'] ?? null])->all(),
                ),
                ...$one('noted_by', 'noted_by'),
            ],
            7 => [...$one('prepared_by'), ...$one('noted_by')],
            8 => [...$one('validated_by'), ...$one('noted_by'), ...$one('approved_by')],
            13 => [...$one('evaluated_by'), ...$one('reviewed_by'), ...$one('noted_by')],
            default => [],
        };
    }

    /**
     * No label = no signatory: blanks the name/position (and Document 8's
     * Validated By contact/date) of every signatory whose label is empty.
     */
    protected function clearUnlabeledSignatories(int $documentNumber, mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $blank = fn (string $key) => trim((string) ($data[$key] ?? '')) === '';
        $roles = match ($documentNumber) {
            6 => [],
            7 => ['prepared_by', 'noted_by'],
            8 => ['validated_by', 'noted_by', 'approved_by'],
            13 => ['evaluated_by', 'reviewed_by', 'noted_by'],
            default => [],
        };

        foreach ($roles as $role) {
            if ($blank("{$role}_label")) {
                $fields = ["{$role}_name", "{$role}_position"];
                if ($role === 'validated_by') {
                    array_push($fields, 'validated_by_contact', 'validated_by_date');
                }
                foreach ($fields as $field) {
                    if (array_key_exists($field, $data)) {
                        $data[$field] = '';
                    }
                }
            }
        }

        if ($documentNumber === 6) {
            if ($blank('prepared_by_label') && is_array($data['prepared_by'] ?? null)) {
                foreach ($data['prepared_by'] as $i => $row) {
                    if (is_array($row)) {
                        $data['prepared_by'][$i]['name'] = '';
                        $data['prepared_by'][$i]['position'] = '';
                    }
                }
            }
            if ($blank('noted_by_label')) {
                $data['noted_by'] = '';
                $data['noted_by_position'] = '';
            }
        }

        return $data;
    }

    protected function assertFieldFormats(string $label, mixed $payload, string $errorKey): void
    {
        if (! is_array($payload)) {
            return;
        }

        $errors = [];
        $this->collectFieldFormatErrors($label, $payload, [], $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages([$errorKey => array_values(array_unique($errors))]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $node
     * @param  list<string>  $trail
     * @param  list<string>  $errors
     */
    private function collectFieldFormatErrors(string $label, array $node, array $trail, array &$errors): void
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->collectFieldFormatErrors($label, $value, is_int($key) ? $trail : [...$trail, (string) $key], $errors);

                continue;
            }

            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            $field = ucfirst(str_replace('_', ' ', trim(implode(' ', [...array_slice($trail, -1), $key]))));

            if (preg_match(self::PERSON_KEY, $key) === 1 && ! PersonName::passes($value)) {
                $errors[] = "{$label}: {$field} may only contain letters and / - ' . , (no numbers or other symbols).";
            } elseif (str_contains($key, 'contact') && ! PhMobile::passes(trim($value))) {
                $errors[] = "{$label}: {$field} must use the format 09XXXXXXXXX or +639XXXXXXXXX.";
            }
        }
    }

    /**
     * How many of Document 7's check-in rows the admin has actually typed
     * something into. The document always seeds 10 blank rows, so a raw count
     * would report 10 the moment it is created — the same filter the founder's
     * Submission page uses to decide which rows are real.
     */
    protected function filledCheckInCount(?array $data): int
    {
        return collect($data['check_ins'] ?? [])
            ->filter(fn ($row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''))
            ->count();
    }
}
