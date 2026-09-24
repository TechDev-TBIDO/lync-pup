<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentDocument;
use App\Models\InformationSheet;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Models\User;
use App\Services\Exports\WordDocumentExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * Signatory blocks across the Assessment Hub:
 *  - no pre-filled names/positions, and a cleared field stays cleared;
 *  - the captions ("Prepared By:" etc.) are editable, blank stays blank;
 *  - in the Word exports a blank signatory is removed and the ones after it
 *    move up, while the last signatory always keeps its right-hand spot;
 *  - multi-line positions keep their line breaks wherever they land.
 */
class SignatoryBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function approvedStartup(): Startup
    {
        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);

        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        return $startup;
    }

    protected function basePayload(array $extra = []): array
    {
        return array_merge([
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode([]),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ], $extra);
    }

    /** Renders a Word export and returns its word/document.xml. */
    protected function renderXml(int $documentNumber, Startup $startup): string
    {
        $binary = app(WordDocumentExporter::class)->render($documentNumber, $startup);
        $this->assertNotNull($binary);

        $path = tempnam(sys_get_temp_dir(), 'sig').'.docx';
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        return $xml;
    }

    /**
     * Text of every paragraph after the last table (where the signatory
     * block lives), trimmed, as [text, rawXml] pairs.
     */
    protected function signatoryParagraphs(string $xml): array
    {
        $tail = substr($xml, strrpos($xml, '</w:tbl>'));
        preg_match_all('/<w:p\b[^>]*\/>|<w:p[ >](?:(?!<w:p[ >]).)*?<\/w:p>/s', $tail, $matches);

        return array_map(function ($p) {
            preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $p, $t);

            // Unicode-aware trim: templates put a non-breaking space after some captions.
            return [preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', html_entity_decode(implode('', $t[1]))), $p];
        }, $matches[0]);
    }

    /** Only the non-empty paragraph texts, in order. */
    protected function texts(string $xml): array
    {
        return array_values(array_filter(array_column($this->signatoryParagraphs($xml), 0), fn ($t) => $t !== ''));
    }

    protected function paragraphContaining(string $xml, string $needle): string
    {
        foreach ($this->signatoryParagraphs($xml) as [$text, $raw]) {
            if (str_contains($text, $needle)) {
                return $raw;
            }
        }
        $this->fail("No paragraph contains '{$needle}'.");
    }

    // ---------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------

    public function test_blank_evaluated_by_saves_blank_instead_of_the_admins_name(): void
    {
        $admin = $this->admin();
        $startup = $this->approvedStartup();

        $this->actingAs($admin)
            ->put(route('admin.assessment-hub.assessments.update', $startup), $this->basePayload())
            ->assertRedirect();

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        $this->assertNull($assessment->mrl_evaluated_by);
        $this->assertNull($assessment->tmrl_evaluated_by);
    }

    public function test_a_deleted_signatory_value_stays_deleted_after_saving(): void
    {
        $admin = $this->admin();
        $startup = $this->approvedStartup();
        $url = route('admin.assessment-hub.assessments.update', $startup);

        $this->actingAs($admin)->put($url, $this->basePayload([
            'approved_by_label' => 'Approved by:',
            'approved_by' => 'Hello Worlds',
            'approved_by_position' => 'Director',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Hello Worlds', ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first()->approved_by);

        // Save again with the whole signatory (label, name, position) emptied.
        $this->actingAs($admin)->put($url, $this->basePayload([
            'approved_by_label' => '',
            'approved_by' => '',
            'approved_by_position' => '',
        ]))->assertSessionHasNoErrors();

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        $this->assertNull($assessment->approved_by);
        $this->assertNull($assessment->approved_by_position);
    }

    public function test_signatory_labels_are_editable_and_a_cleared_label_stays_blank(): void
    {
        $admin = $this->admin();
        $startup = $this->approvedStartup();

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), $this->basePayload([
            'prepared_by_label' => 'Inihanda ni:',
            'prepared_by' => 'Ana Cruz',
            'prepared_by_position' => 'Chief',
            'approved_by_label' => '',
            'srl_noted_by_label' => 'Nakita ni:',
            'srl_noted_by' => 'Ben Reyes',
            'srl_noted_by_position' => 'Director',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        $this->assertSame('Inihanda ni:', $assessment->prepared_by_label);
        $this->assertNull($assessment->approved_by_label);
        $this->assertSame('Nakita ni:', $assessment->srl_noted_by_label);
    }

    public function test_a_filled_label_requires_name_and_position(): void
    {
        $admin = $this->admin();
        $startup = $this->approvedStartup();
        $url = route('admin.assessment-hub.assessments.update', $startup);

        // Label filled, position missing -> rejected, nothing saved.
        $this->actingAs($admin)->put($url, $this->basePayload([
            'mrl_reviewed_by_label' => 'Reviewed by:',
            'mrl_reviewed_by' => 'Ana Cruz',
        ]))->assertSessionHasErrors('signatories');
        $this->assertSame(0, ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->count());

        // No label = no signatory: a name sent without a label is not kept.
        $this->actingAs($admin)->put($url, $this->basePayload([
            'mrl_reviewed_by' => 'Ana Cruz',
            'mrl_reviewed_by_position' => 'Chief',
        ]))->assertSessionHasNoErrors();
        $saved = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        $this->assertNull($saved->mrl_reviewed_by);
        $this->assertNull($saved->mrl_reviewed_by_position);

        // Label with both -> saved.
        $this->actingAs($admin)->put($url, $this->basePayload([
            'mrl_reviewed_by_label' => 'Reviewed by:',
            'mrl_reviewed_by' => 'Ana Cruz',
            'mrl_reviewed_by_position' => 'Chief',
        ]))->assertSessionHasNoErrors();
    }

    public function test_a_filled_label_requires_name_and_position_on_documents(): void
    {
        $admin = $this->admin();
        $startup = $this->approvedStartup();
        $url = route('admin.assessment-hub.assessments.update-documents', $startup);

        $this->actingAs($admin)->put($url, [
            'stage' => 'Active-Assessment',
            'active_document' => 7,
            'document_7' => json_encode(['noted_by_label' => 'Noted By:', 'noted_by_name' => '', 'noted_by_position' => '']),
        ])->assertSessionHasErrors('signatories');

        $this->actingAs($admin)->put($url, [
            'stage' => 'Venture Exit',
            'document_13' => json_encode(['evaluated_by_label' => 'Evaluated by:', 'evaluated_by_name' => 'Ana', 'evaluated_by_position' => 'Chief']),
        ])->assertSessionHasNoErrors();

        // No label = no signatory: Document 8's Noted By sent without a label is emptied.
        $this->actingAs($admin)->put($url, [
            'stage' => 'Active-Assessment',
            'active_document' => 8,
            'document_8' => json_encode(['noted_by_label' => '', 'noted_by_name' => 'Ana', 'noted_by_position' => 'Chief']),
        ])->assertSessionHasNoErrors();
        $doc8 = AssessmentDocument::where('startup_id', $startup->startup_id)->where('document_number', 8)->first();
        $this->assertSame('', $doc8->data['noted_by_name']);
        $this->assertSame('', $doc8->data['noted_by_position']);

        // Document 6: shared Prepared By label needs at least one complete signer.
        $this->actingAs($admin)->put($url, [
            'stage' => 'Active-Assessment',
            'active_document' => 6,
            'document_6' => json_encode(['prepared_by_label' => 'Prepared By:', 'prepared_by' => [['name' => '', 'position' => '']]]),
        ])->assertSessionHasErrors('signatories');

        $this->actingAs($admin)->put($url, [
            'stage' => 'Active-Assessment',
            'active_document' => 6,
            'document_6' => json_encode(['prepared_by_label' => 'Prepared By:', 'prepared_by' => [
                ['name' => 'Ana', 'position' => 'Chief'], ['name' => '', 'position' => ''],
            ]]),
        ])->assertSessionHasNoErrors();
    }

    public function test_the_form_has_no_prefilled_signatory_names_or_positions(): void
    {
        $admin = $this->admin();
        $startup = $this->approvedStartup();

        foreach (['Pre-Assessment', 'Post-Assessment', 'Active-Assessment', 'Venture Exit'] as $stage) {
            $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
                'main' => 'assessment',
                'stage' => $stage,
                'assessment_startup' => $startup->startup_id,
            ]));

            $response->assertOk();
            foreach (['ERMITA', 'ESPINELI', 'Portfolio Coordinator, TBIDO', 'Director, TBIDO', 'Chief, TBIDO'] as $prefill) {
                $response->assertDontSee($prefill, false);
            }
        }
    }

    // ---------------------------------------------------------------
    // Word exports — every Assessment Hub document
    // ---------------------------------------------------------------

    /**
     * Documents whose signatories are columns on ReadinessLevelAssessment:
     * [document number, stage, [slot1, slot2, last] column prefixes,
     *  indent marking the last signatory's right-hand position].
     */
    public static function assessmentDocuments(): array
    {
        $rl = fn (string $t) => ["{$t}_evaluated_by", "{$t}_reviewed_by", "{$t}_noted_by"];

        return [
            'Pre TRL (doc 2)' => [2, 'Pre-Assessment', ['prepared_by', 'trl_noted_by', 'approved_by'], 'w:left="3600"'],
            'Pre MRL (doc 3)' => [3, 'Pre-Assessment', $rl('mrl'), 'w:left="5760"'],
            'Pre TMRL (doc 4)' => [4, 'Pre-Assessment', $rl('tmrl'), 'w:left="5760"'],
            'Pre SRL (doc 5)' => [5, 'Pre-Assessment', $rl('srl'), 'w:left="5760"'],
            // The Post TRL template indents Approved by less than the Pre one.
            'Post TRL (doc 9)' => [9, 'Post-Assessment', ['prepared_by', 'trl_noted_by', 'approved_by'], 'w:left="2880"'],
            'Post MRL (doc 10)' => [10, 'Post-Assessment', $rl('mrl'), 'w:left="5760"'],
            'Post TMRL (doc 11)' => [11, 'Post-Assessment', $rl('tmrl'), 'w:left="5760"'],
            'Post SRL (doc 12)' => [12, 'Post-Assessment', $rl('srl'), 'w:left="5760"'],
        ];
    }

    /**
     * Documents whose signatories live in the JSON data column:
     * [document number, stage, [role keys in slot order], indent of the
     *  last signatory, whether names print in capitals].
     */
    public static function jsonDocuments(): array
    {
        return [
            'Document 7' => [7, 'Active-Assessment', ['prepared_by', 'noted_by'], 'w:left="4320"', false],
            'Document 8' => [8, 'Active-Assessment', ['noted_by', 'approved_by'], 'w:left="2880"', false],
            'Venture Exit (doc 13)' => [13, 'Venture Exit', ['evaluated_by', 'reviewed_by', 'noted_by'], 'w:left="5040"', true],
        ];
    }

    /** Label / name / position for signatory $i (1-based). */
    protected function person(int $i): array
    {
        return ["Label {$i}:", "Person {$i}", "Title {$i}"];
    }

    /** Expected export text for the given signatories, in order. */
    protected function expected(array $numbers, bool $upper = true): array
    {
        $out = [];
        foreach ($numbers as $i) {
            [$label, $name, $title] = $this->person($i);
            array_push($out, $label, $upper ? mb_strtoupper($name) : $name, $title);
        }

        return $out;
    }

    protected function makeAssessment(Startup $startup, string $stage, array $prefixes, array $filled): void
    {
        $attributes = ['startup_id' => $startup->startup_id, 'stage' => $stage];
        foreach ($prefixes as $k => $prefix) {
            $i = $k + 1;
            [$label, $name, $title] = in_array($i, $filled, true) ? $this->person($i) : [null, null, null];
            $attributes["{$prefix}_label"] = $label;
            $attributes[$prefix] = $name;
            $attributes["{$prefix}_position"] = $title;
        }
        ReadinessLevelAssessment::factory()->create($attributes);
    }

    protected function makeDocument(Startup $startup, int $number, string $stage, array $roles, array $filled): void
    {
        $data = [];
        foreach ($roles as $k => $role) {
            $i = $k + 1;
            [$label, $name, $title] = in_array($i, $filled, true) ? $this->person($i) : ['', '', ''];
            $data["{$role}_label"] = $label;
            $data["{$role}_name"] = $name;
            $data["{$role}_position"] = $title;
        }
        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => $stage,
            'document_number' => $number,
            'data' => $data,
        ]);
    }

    /** Asserts the export's signatory block reads exactly $expected. */
    protected function assertSignatoryText(string $xml, array $expected): void
    {
        $this->assertStringNotContainsString('${', $xml, 'A placeholder was left unfilled.');
        $this->assertStringNotContainsString('sig_slot', $xml, 'A slot marker was left in the document.');
        $texts = $this->texts($xml);
        $this->assertSame($expected, array_slice($texts, -count($expected)));
        foreach (['Label 1:', 'Label 2:', 'Label 3:'] as $label) {
            if (! in_array($label, $expected, true)) {
                $this->assertNotContains($label, $texts);
            }
        }
    }

    /** Asserts exactly $count blank lines between the last table and the first signatory. */
    protected function assertBlankLinesAfterTable(string $xml, int $count): void
    {
        $all = array_column($this->signatoryParagraphs($xml), 0);
        $blanks = 0;
        foreach ($all as $text) {
            if ($text !== '') {
                break;
            }
            $blanks++;
        }
        $this->assertSame($count, $blanks, "Expected {$count} blank lines between the table and the first signatory.");
    }

    /** The nearest non-blank paragraph text above the paragraph reading $text. */
    protected function nonBlankBefore(string $xml, string $text): string
    {
        $all = array_column($this->signatoryParagraphs($xml), 0);
        $at = array_search($text, $all, true);
        $this->assertNotFalse($at);
        for ($i = $at - 1; $i >= 0; $i--) {
            if ($all[$i] !== '') {
                // Blank lines between must number exactly 2.
                $this->assertSame(2, $at - $i - 1, "Expected exactly 2 blank lines before '{$text}'.");

                return $all[$i];
            }
        }
        $this->fail("Nothing above '{$text}'.");
    }

    /** Asserts exactly two blank lines right before the paragraph reading $text. */
    protected function assertTwoBlankLinesBefore(string $xml, string $text): void
    {
        $all = array_column($this->signatoryParagraphs($xml), 0);
        $at = array_search($text, $all, true);
        $this->assertNotFalse($at);
        $this->assertSame(['', ''], array_slice($all, $at - 2, 2), "Expected 2 blank lines before '{$text}'.");
    }

    #[DataProvider('assessmentDocuments')]
    public function test_all_signatories_filled(int $doc, string $stage, array $prefixes, string $rightIndent): void
    {
        $startup = $this->approvedStartup();
        $this->makeAssessment($startup, $stage, $prefixes, [1, 2, 3]);
        $xml = $this->renderXml($doc, $startup);

        $this->assertSignatoryText($xml, $this->expected([1, 2, 3]));
        $this->assertBlankLinesAfterTable($xml, 2);
        $this->assertTwoBlankLinesBefore($xml, 'Label 2:');
        $this->assertStringContainsString($rightIndent, $this->paragraphContaining($xml, 'Label 3:'));
    }

    #[DataProvider('assessmentDocuments')]
    public function test_first_signatory_blank_moves_the_second_up(int $doc, string $stage, array $prefixes, string $rightIndent): void
    {
        $startup = $this->approvedStartup();
        $this->makeAssessment($startup, $stage, $prefixes, [2, 3]);
        $xml = $this->renderXml($doc, $startup);

        $this->assertSignatoryText($xml, $this->expected([2, 3]));
        $this->assertStringNotContainsString($rightIndent, $this->paragraphContaining($xml, 'Label 2:'));
        $this->assertStringContainsString($rightIndent, $this->paragraphContaining($xml, 'Label 3:'));
    }

    #[DataProvider('assessmentDocuments')]
    public function test_middle_signatory_blank_closes_the_gap(int $doc, string $stage, array $prefixes, string $rightIndent): void
    {
        $startup = $this->approvedStartup();
        $this->makeAssessment($startup, $stage, $prefixes, [1, 3]);
        $xml = $this->renderXml($doc, $startup);

        $this->assertSignatoryText($xml, $this->expected([1, 3]));
        $this->assertTwoBlankLinesBefore($xml, 'Label 3:');
        $this->assertSame('Title 1', $this->nonBlankBefore($xml, 'Label 3:'), 'Only 2 blank lines should separate the two signatories.');
        $this->assertStringContainsString($rightIndent, $this->paragraphContaining($xml, 'Label 3:'));
    }

    #[DataProvider('assessmentDocuments')]
    public function test_only_last_signatory_stays_on_the_right(int $doc, string $stage, array $prefixes, string $rightIndent): void
    {
        $startup = $this->approvedStartup();
        $this->makeAssessment($startup, $stage, $prefixes, [3]);
        $xml = $this->renderXml($doc, $startup);

        $this->assertSignatoryText($xml, $this->expected([3]));
        $this->assertStringContainsString($rightIndent, $this->paragraphContaining($xml, 'Label 3:'));
    }

    #[DataProvider('assessmentDocuments')]
    public function test_multi_line_position_keeps_its_line_breaks(int $doc, string $stage, array $prefixes, string $rightIndent): void
    {
        $startup = $this->approvedStartup();
        // Second signatory moves into the first slot; its two-line position must not be joined.
        ReadinessLevelAssessment::factory()->create([
            'startup_id' => $startup->startup_id,
            'stage' => $stage,
            $prefixes[1] => 'Kaeya',
            "{$prefixes[1]}_position" => "Line One\nLine Two",
        ]);
        $xml = $this->renderXml($doc, $startup);

        $this->assertMatchesRegularExpression('/Line One(<\/w:t>.*?<w:t[^>]*>|<\/w:t><w:br\/><w:t[^>]*>)Line Two/s', $xml);
        $this->assertStringNotContainsString('Line One, Line Two', $xml);
    }

    #[DataProvider('jsonDocuments')]
    public function test_json_document_all_filled(int $doc, string $stage, array $roles, string $rightIndent, bool $upper): void
    {
        $startup = $this->approvedStartup();
        $all = range(1, count($roles));
        $this->makeDocument($startup, $doc, $stage, $roles, $all);
        $xml = $this->renderXml($doc, $startup);

        $this->assertSignatoryText($xml, $this->expected($all, $upper));
        if ($doc !== 8) {
            $this->assertBlankLinesAfterTable($xml, 2);
        }
        $this->assertStringContainsString($rightIndent, $this->paragraphContaining($xml, 'Label '.count($roles).':'));
    }

    #[DataProvider('jsonDocuments')]
    public function test_json_document_first_blank_moves_the_next_up(int $doc, string $stage, array $roles, string $rightIndent, bool $upper): void
    {
        $startup = $this->approvedStartup();
        $filled = range(2, count($roles));
        $this->makeDocument($startup, $doc, $stage, $roles, $filled);
        $xml = $this->renderXml($doc, $startup);
        $last = 'Label '.count($roles).':';

        $this->assertSignatoryText($xml, $this->expected($filled, $upper));
        $this->assertStringContainsString($rightIndent, $this->paragraphContaining($xml, $last));
        if (count($roles) === 3) {
            $this->assertStringNotContainsString($rightIndent, $this->paragraphContaining($xml, 'Label 2:'));
        }
    }

    public function test_venture_exit_middle_blank_closes_the_gap_with_two_blank_lines(): void
    {
        $startup = $this->approvedStartup();
        $this->makeDocument($startup, 13, 'Venture Exit', ['evaluated_by', 'reviewed_by', 'noted_by'], [1, 3]);
        $xml = $this->renderXml(13, $startup);

        $this->assertSignatoryText($xml, $this->expected([1, 3]));
        $this->assertTwoBlankLinesBefore($xml, 'Label 3:');
        $this->assertSame('Title 1', $this->nonBlankBefore($xml, 'Label 3:'));

        // All filled: two blank lines between Evaluated by and Reviewed by.
        AssessmentDocument::query()->delete();
        $this->makeDocument($startup, 13, 'Venture Exit', ['evaluated_by', 'reviewed_by', 'noted_by'], [1, 2, 3]);
        $this->assertTwoBlankLinesBefore($this->renderXml(13, $startup), 'Label 2:');
    }

    public function test_document_6_prepared_by_signers_close_up_gaps(): void
    {
        $startup = $this->approvedStartup();
        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Active-Assessment',
            'document_number' => 6,
            'data' => [
                'prepared_by_label' => 'Prepared By:',
                'prepared_by' => [
                    ['name' => '', 'position' => ''],
                    ['name' => 'Second Signer', 'position' => 'Chief'],
                    ['name' => '', 'position' => ''],
                ],
                'noted_by_label' => 'Noted By:',
                'noted_by' => 'Cara',
                'noted_by_position' => 'Director',
            ],
        ]);

        $xml = $this->renderXml(6, $startup);

        $this->assertStringNotContainsString('sig_prep', $xml);
        $this->assertSame(
            ['Prepared By:', 'SECOND SIGNER', 'Chief', 'Noted By:', 'CARA', 'Director'],
            array_slice($this->texts($xml), -6)
        );
        $this->assertStringContainsString('w:left="6480"', $this->paragraphContaining($xml, 'Noted By:'));
    }
}
