<?php

namespace Tests\Unit;

use App\Notifications\InformationSheetRejected;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The dashboard card splices the admin's free-typed remarks into the middle
 * of a sentence, so it has to fix up punctuation itself — see
 * InformationSheetRejected::sentenceRemarks().
 */
class InformationSheetRejectedTest extends TestCase
{
    public static function remarksProvider(): array
    {
        return [
            'no punctuation' => ['Missing signature on page 2', 'evaluation: Missing signature on page 2. You may revise'],
            'trailing spaces' => ['  Missing signature on page 2  ', 'evaluation: Missing signature on page 2. You may revise'],
            'already a period' => ['Missing signature on page 2.', 'evaluation: Missing signature on page 2. You may revise'],
            'question mark' => ['Where is the signature?', 'evaluation: Where is the signature? You may revise'],
            'exclamation' => ['Incomplete!', 'evaluation: Incomplete! You may revise'],
            'period inside quotes' => ['Fix the "Team" section.”', 'evaluation: Fix the "Team" section.” You may revise'],
            'ends in a bracket' => ['Missing signature (page 2)', 'evaluation: Missing signature (page 2). You may revise'],
            'line breaks folded' => ["Missing signature\n\nand the date", 'evaluation: Missing signature and the date. You may revise'],
        ];
    }

    #[DataProvider('remarksProvider')]
    public function test_remarks_always_read_as_their_own_sentence(string $remarks, string $expectedFragment): void
    {
        $body = (new InformationSheetRejected($remarks))->body();

        $this->assertStringContainsString($expectedFragment, $body);
        $this->assertStringNotContainsString('..', $body);
    }

    public function test_blank_remarks_fall_back_to_the_plain_sentence(): void
    {
        foreach ([null, '', "  \n "] as $remarks) {
            $this->assertSame(
                'TBIDO Form No.001 was not accepted this evaluation. You may revise and resubmit it.',
                (new InformationSheetRejected($remarks))->body()
            );
        }
    }
}
