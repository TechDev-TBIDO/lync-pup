<?php

namespace App\Services;

use App\Models\AssessmentDocument;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Support\ReadinessRubric;
use App\Support\VentureExitForm;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drafts the Venture Exit document's (Document 13) judgment-call fields
 * from a startup's existing assessment history, via Google's Gemini API —
 * the Graduation Readiness checklist's Status+Remark, the three Final
 * Evaluation and Exit Support Plan textareas, and the Post Program
 * Readiness Level table's Highest Level + Remarks columns. A genuinely
 * saved Post-Assessment score always wins over an AI guess — the view
 * (_venture-exit.blade.php) only lets an AI-drafted Highest Level fill a
 * field that's currently blank, never overwrite a real one. Never saves
 * anything itself: this is a first draft only, returned to the admin's
 * screen for them to review, edit, and save through the normal Save
 * Assessment flow (AssessmentController::updateDocuments()).
 */
class VentureExitAiGenerator
{
    public function generate(Startup $startup): array
    {
        $apiKey = config('services.gemini.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('AI generation isn\'t configured yet — add a GEMINI_API_KEY to the .env file (a free key is available at aistudio.google.com).');
        }

        $model = config('services.gemini.model', 'gemini-3.6-flash');

        // PHP's own script timeout (max_execution_time, often 30s by
        // default) was killing this request before the Gemini call even
        // finished — completely separate from the Http timeout below, and
        // not something a try/catch here can intercept. Raise it just for
        // this request rather than touching php.ini globally. Bumped up
        // from 90s to cover the retry below's worst case (two 75s attempts
        // plus the sleep between them) without PHP cutting it off first.
        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        // Gemini's REST API authenticates via a "?key=" query string, not a
        // Bearer header — appended directly to the URL rather than relying
        // on a query-params helper that may not exist on every Http client
        // version.
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".urlencode($apiKey);

        // Gemini frequently answers with a 503 "this model is currently
        // experiencing high demand" when Google's own servers are
        // momentarily overloaded — nothing wrong with our request, and
        // nothing a paid API key changes (that's a rate-limit/quota lever,
        // this is server capacity). Google's own guidance for this exact
        // error is to retry shortly after, since spikes are usually brief.
        // One retry (two attempts total), a few seconds apart, clears most
        // of these without the admin ever seeing an error. Configuring
        // ->retry() makes a failed response throw internally so this `when`
        // callback can inspect it — throw: false means that, if both
        // attempts fail, the method falls through to the normal
        // $response->failed() handling below instead of throwing here.
        // Deliberately narrow to 503 only: a bad API key or malformed
        // request (4xx) would just fail the same way twice, wasting the
        // admin's time waiting on a retry that was never going to help.
        $response = Http::timeout(75)
            ->retry(2, 3000, function (\Throwable $exception) {
                return $exception instanceof RequestException
                    && $exception->response->status() === 503;
            }, throw: false)
            ->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemPrompt()]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $this->buildContext($startup)]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'responseMimeType' => 'application/json',
                    // Tried disabling "thinking" here to cut latency, but the
                    // classic generateContent endpoint rejects thinking-control
                    // fields on current Gemini 3.x models ("Request contains an
                    // invalid argument") — that control has moved to Google's
                    // separate Interactions API, which this service doesn't
                    // use. Left plain; the timeout raise above is what actually
                    // fixes the original 30s cutoff.
                ],
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message') ?: 'The AI service returned an error. Please try again.';

            throw new RuntimeException($this->explainGeminiError($message));
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (blank($content)) {
            throw new RuntimeException('The AI service returned an empty response. Please try again.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The AI service returned a response that could not be read. Please try again.');
        }

        return $this->normalize($decoded);
    }

    /**
     * Gemini's raw error text is accurate but assumes familiarity with its
     * API — this appends a plain-language hint for the handful of causes
     * that keep coming up when different people set up their own
     * GEMINI_API_KEY (each admin needs their own; .env is never shared via
     * git), so whoever hits it can self-diagnose instead of having to ask.
     * The original message is always kept, just with a hint tacked on.
     */
    protected function explainGeminiError(string $message): string
    {
        $hint = match (true) {
            // Gemini's free-tier model lineup is retired/renamed often —
            // a model name that worked when this was built can stop
            // working for newly-created keys within months.
            str_contains($message, 'is not found for API') || str_contains($message, 'not supported for generateContent') =>
                'The model name in GEMINI_MODEL (config/services.php or .env) is no longer available. '
                .'Check https://ai.google.dev/gemini-api/docs/models for a current free-tier model name and update GEMINI_MODEL to match.',

            str_contains($message, 'API key not valid') =>
                'Double-check the value saved as GEMINI_API_KEY in .env — it looks like it was copied incorrectly, '
                .'or the key was deleted/regenerated. Get a fresh one at https://aistudio.google.com/apikey.',

            str_contains($message, 'invalid authentication credentials') || str_contains($message, 'OAuth 2 access token') =>
                'Google didn\'t recognize this as a Generative Language API key — this usually means the value in '
                .'GEMINI_API_KEY is the wrong kind of credential (e.g. a different Google Cloud key/token, not an '
                .'AI Studio key). Generate one specifically from https://aistudio.google.com/apikey and use that.',

            // Already retried twice (see generate()) before reaching here —
            // this is Google's own servers being temporarily overloaded,
            // not anything wrong with this app's setup or API key, and not
            // something a paid-tier key changes (that raises rate limits,
            // not server capacity). Simplest fix is just trying again in a
            // minute or two.
            str_contains($message, 'high demand') || str_contains($message, 'overloaded') =>
                'This is Google\'s own servers being temporarily overloaded, not a problem with this app\'s setup — '
                .'already retried automatically without success. Please wait a minute or two and try again.',

            default => null,
        };

        return $hint ? "{$message} — {$hint}" : $message;
    }

    /**
     * Strict output-shape instructions, listing the exact indicator/type
     * names the model must use — matched verbatim against
     * VentureExitForm/ReadinessRubric below in normalize(), so any
     * indicator the model mis-names or skips simply falls back to a blank
     * row instead of breaking the page.
     */
    protected function systemPrompt(): string
    {
        $indicatorList = collect(VentureExitForm::GRADUATION_READINESS_INDICATORS)
            ->map(fn ($i) => "  - \"{$i}\"")
            ->implode("\n");

        $typeList = collect(ReadinessRubric::TYPES)
            ->map(fn ($t) => "  - \"{$t}\"")
            ->implode("\n");

        return <<<PROMPT
        You are helping an admin at a university startup incubator draft a Venture
        Exit evaluation (PUP-TBIDO Form No. 013) for a startup that is completing
        the incubation program. The admin will review and edit everything you write
        before saving it, so write a solid, honest first draft — do not invent
        facts, numbers, or achievements that are not supported by the startup data
        given to you in the user message. Where the evidence is thin or missing,
        say so plainly in the remark rather than guessing or padding it out.

        Respond with ONLY a single JSON object and nothing else (no markdown, no
        commentary), matching exactly this shape:

        {
          "graduation_readiness": {
        {$indicatorList}
            // one object per indicator above, each shaped:
            // {"status": true or false, "remark": "one or two sentences"}
          },
          "final_evaluation": {
            "summary_of_progress": "a short paragraph summarizing the startup's progress during incubation",
            "post_incubation_recommendation": "a short paragraph recommending what should happen after incubation",
            "scale_up_linkages": "a short paragraph on scale-up support, partners, or linkages worth pursuing"
          },
          "readiness_levels": {
        {$typeList}
            // one object per type above, each shaped:
            // {"highest_level": "X.X/9", "remarks": "one or two sentences explaining that score"}
            // For highest_level: if the startup data includes an actual
            // Post-Assessment score for that type, copy that exact number
            // (it is a real decimal like "6.3/9", not a whole number — do
            // not round it to the nearest whole level). If there is no
            // Post-Assessment score, give your own best-estimate score (0-9,
            // one decimal place) based on the Pre-Assessment score (if any)
            // and everything else in the data, formatted as "X.X/9". If
            // there truly isn't enough evidence to estimate anything, leave
            // it as an empty string rather than guessing blindly.
          }
        }
        PROMPT;
    }

    /**
     * Everything about this startup worth grounding the draft in: its
     * Information Sheet basics, its Pre/Post-Assessment readiness scores,
     * its Active-Assessment documents (6/7/8) raw data, and its roadblock
     * history. Dumped as plain labeled text (raw JSON for the documents,
     * since their exact field shape isn't otherwise needed here) rather
     * than a rigid schema — the model only needs to read it, not parse it.
     */
    protected function buildContext(Startup $startup): string
    {
        $startup->loadMissing(['informationSheet']);
        $sheet = $startup->informationSheet;

        $lines = [];
        $lines[] = "Startup name: {$startup->company_name}";
        $lines[] = 'Industry: '.($startup->industry_sector ?: 'Not specified');
        $lines[] = 'Cohort: '.($startup->batch_label ?? 'Not specified');

        if ($sheet) {
            $lines[] = "\n--- Information Sheet ---";
            $lines[] = 'Business description: '.($sheet->business_description ?: '—');
            $lines[] = 'Startup overview: '.($sheet->startup_overview ?: '—');
            $lines[] = 'Target market: '.($sheet->target_market ?: '—');
            $lines[] = 'Problem statement: '.($sheet->problem_statement ?: '—');
            $lines[] = 'Solution offered: '.($sheet->solution_offered ?: '—');

            $legal = collect([
                'SEC registration' => $sheet->sec_registration,
                'Business ID number' => $sheet->business_id_number,
                'DTI registration number' => $sheet->dti_registration_number,
                'Business TIN' => $sheet->business_tin,
            ])->filter(fn ($value) => filled($value));

            $lines[] = 'Legal/registration on file: '.(
                $legal->isNotEmpty()
                    ? $legal->map(fn ($value, $label) => "{$label}: {$value}")->implode('; ')
                    : 'None recorded'
            );
        }

        foreach (['Pre-Assessment', 'Post-Assessment'] as $stage) {
            $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
                ->where('stage', $stage)
                ->first();

            if (! $assessment) {
                continue;
            }

            $lines[] = "\n--- {$stage} Readiness Scores ---";

            foreach (ReadinessRubric::TYPES as $type) {
                $score = $assessment->scoreFor($type);
                $lines[] = "{$type}: ".($score !== null ? number_format($score, 1).'/9' : 'Not assessed');
            }

            if (filled($assessment->remarks)) {
                $lines[] = 'Assessor remarks: '.$assessment->remarks;
            }
        }

        $activeDocuments = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->whereIn('document_number', [6, 7, 8])
            ->get();

        if ($activeDocuments->isNotEmpty()) {
            $lines[] = "\n--- Active-Assessment Documents (raw form data) ---";

            foreach ($activeDocuments as $document) {
                $lines[] = "Document {$document->document_number}: ".json_encode($document->data);
            }
        }

        $roadblocks = $startup->roadblocks()->orderByDesc('created_at')->limit(20)->get();

        if ($roadblocks->isNotEmpty()) {
            $lines[] = "\n--- Roadblocks History (most recent first) ---";

            foreach ($roadblocks as $roadblock) {
                $description = Str::limit((string) $roadblock->description, 200);
                $lines[] = "- [{$roadblock->status}] {$roadblock->display_category}: {$description}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Coerces the model's (possibly incomplete or oddly-shaped) JSON into
     * exactly the fields the Venture Exit view expects — one entry per
     * indicator/type the app actually defines, defaulting to a blank/false
     * row for anything the model skipped or mis-named, so a partial AI
     * response degrades gracefully instead of leaving the form half-wired.
     */
    protected function normalize(array $decoded): array
    {
        $graduationReadiness = [];

        foreach (VentureExitForm::GRADUATION_READINESS_INDICATORS as $indicator) {
            $row = $decoded['graduation_readiness'][$indicator] ?? [];

            $graduationReadiness[$indicator] = [
                'status' => (bool) ($row['status'] ?? false),
                'remark' => is_string($row['remark'] ?? null) ? trim($row['remark']) : '',
            ];
        }

        $finalEvaluation = is_array($decoded['final_evaluation'] ?? null) ? $decoded['final_evaluation'] : [];

        $readinessLevels = [];

        foreach (ReadinessRubric::TYPES as $type) {
            $row = $decoded['readiness_levels'][$type] ?? [];

            $readinessLevels[$type] = [
                'highest_level' => is_string($row['highest_level'] ?? null) ? trim($row['highest_level']) : '',
                'remarks' => is_string($row['remarks'] ?? null) ? trim($row['remarks']) : '',
            ];
        }

        return [
            'graduation_readiness' => $graduationReadiness,
            'summary_of_progress' => is_string($finalEvaluation['summary_of_progress'] ?? null)
                ? trim($finalEvaluation['summary_of_progress']) : '',
            'post_incubation_recommendation' => is_string($finalEvaluation['post_incubation_recommendation'] ?? null)
                ? trim($finalEvaluation['post_incubation_recommendation']) : '',
            'scale_up_linkages' => is_string($finalEvaluation['scale_up_linkages'] ?? null)
                ? trim($finalEvaluation['scale_up_linkages']) : '',
            'readiness_levels' => $readinessLevels,
        ];
    }
}
