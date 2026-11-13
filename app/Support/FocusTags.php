<?php

namespace App\Support;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;

/**
 * The fields of focus an admin can tag a mentor or coordinator with: the
 * roadblock types founders can ask for help with, and the industries startups
 * work in. Both include values already in use, since founders can type their
 * own industry or an "Others" roadblock type. Fields an admin adds by hand on a
 * mentor or coordinator become options for everyone else too.
 */
class FocusTags
{
    public const ROADBLOCK_TYPES = ['Business Development', 'Technical Support', 'Market Research', 'Strategy Consultant'];

    /** @return list<string> Every field on offer, alphabetically, without duplicates. */
    public static function options(): array
    {
        return once(fn () => self::unique([
            ...self::ROADBLOCK_TYPES,
            ...Roadblock::where('problem_category', 'Others')->whereNotNull('problem_category_other')->distinct()->pluck('problem_category_other'),
            ...TrlOverviewForm::INDUSTRY_FOCUS,
            ...Startup::whereNotNull('industry_sector')->distinct()->pluck('industry_sector'),
            ...Mentor::whereNotNull('focus_tags')->pluck('focus_tags')->flatten(),
            ...Coordinator::whereNotNull('focus_tags')->pluck('focus_tags')->flatten(),
        ]));
    }

    /** So "Health Tech", "healthtech" and "HealthTech" all count as the same field. */
    public static function normalize(?string $tag): string
    {
        return mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', '', (string) $tag));
    }

    /**
     * @param  iterable<string|null>  $tags
     * @return list<string>
     */
    private static function unique(iterable $tags): array
    {
        $unique = [];

        foreach ($tags as $tag) {
            $key = self::normalize($tag);

            if ($key !== '' && ! isset($unique[$key])) {
                $unique[$key] = trim($tag);
            }
        }

        natcasesort($unique);

        return array_values($unique);
    }
}
