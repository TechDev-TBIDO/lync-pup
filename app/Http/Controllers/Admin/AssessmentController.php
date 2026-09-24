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
use App\Support\ActiveAssessmentForms;
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
        // Each defaults to the current admin's name/email on this
        // assessment's first save, same behavior the old shared
        // evaluated_by column used to have — now applied independently
        // for MRL and TMRL since they no longer share one column.
        $assessment->mrl_evaluated_by = $validated['mrl_evaluated_by'] ?? ($request->user()->name ?? $request->user()->email);
        $assessment->mrl_evaluated_by_position = $validated['mrl_evaluated_by_position'] ?? null;
        $assessment->mrl_reviewed_by = $validated['mrl_reviewed_by'] ?? null;
        $assessment->mrl_reviewed_by_position = $validated['mrl_reviewed_by_position'] ?? null;
        $assessment->mrl_noted_by = $validated['mrl_noted_by'] ?? null;
        $assessment->mrl_noted_by_position = $validated['mrl_noted_by_position'] ?? null;
        $assessment->tmrl_evaluated_by = $validated['tmrl_evaluated_by'] ?? ($request->user()->name ?? $request->user()->email);
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

        // Check every document's names/positions/contact numbers BEFORE any of
        // them is written - one bad field rejects the whole save instead of
        // leaving the other documents half-updated.
        foreach ([6, 7, 8, 13] as $documentNumber) {
            $key = 'document_'.$documentNumber;

            if (array_key_exists($key, $validated)) {
                $this->assertFieldFormats('Document '.$documentNumber, json_decode($validated[$key], true), $key);
            }
        }

        // Active-Assessment documents can't be saved with a blank signatory
        // (Prepared / Noted / Validated / Approved By) - same rule the Save
        // button enforces in the browser; see ActiveAssessmentForms::documentProblems(). Only the
        // documents the admin actually changed this time are held to it
        // (changed_documents, sent by the form), since all three are posted
        // on every save and an untouched tab must not block saving another.
        if ($validated['stage'] === 'Active-Assessment') {
            $changed = array_map('intval', (array) (json_decode((string) $request->input('changed_documents', '[]'), true) ?: []));

            foreach ([6, 7, 8] as $documentNumber) {
                $key = 'document_'.$documentNumber;

                if (! in_array($documentNumber, $changed, true) || ! array_key_exists($key, $validated)) {
                    continue;
                }

                $problems = ActiveAssessmentForms::documentProblems($documentNumber, json_decode((string) $validated[$key], true) ?: []);

                if ($problems !== []) {
                    throw ValidationException::withMessages([$key => $problems]);
                }
            }
        }

        $changes = [];

        foreach ([6, 7, 8, 13] as $documentNumber) {
            $key = 'document_'.$documentNumber;

            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $payload = json_decode($validated[$key], true);

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
