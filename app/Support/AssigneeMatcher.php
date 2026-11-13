<?php

namespace App\Support;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use Illuminate\Support\Collection;

/**
 * The fallback for App\Services\AiAssigneeMatcher when AI isn't available: ranks
 * mentors and coordinators by tags with the same name as what a startup needs.
 * Each need covered adds its weight to their score, and ties go to whoever has
 * the lighter workload. These are only suggestions: the admin still decides.
 *
 * Results only include people who match at least one need, best first, as:
 * ['key', 'name', 'type', 'label', 'matched', 'reason', 'workload', 'score'].
 */
class AssigneeMatcher
{
    /** The problem a founder raised matters more than their general industry. */
    private const ROADBLOCK_TYPE_WEIGHT = 3;

    private const ROADBLOCK_INDUSTRY_WEIGHT = 2;

    /** A coordinator stays with a startup long term, so its industry comes first. */
    private const STARTUP_INDUSTRY_WEIGHT = 3;

    private const OPEN_ROADBLOCK_WEIGHT = 1;

    /**
     * @param  iterable<Mentor>  $mentors
     * @param  iterable<Coordinator>  $coordinators
     */
    public static function forRoadblock(Roadblock $roadblock, iterable $mentors, iterable $coordinators): Collection
    {
        $needs = self::needs([
            [$roadblock->display_category, self::ROADBLOCK_TYPE_WEIGHT],
            [$roadblock->startup?->industry_sector, self::ROADBLOCK_INDUSTRY_WEIGHT],
        ]);

        return self::rank($needs, self::roadblockCandidates($mentors, $coordinators));
    }

    /**
     * @param  iterable<Mentor>  $mentors
     * @param  iterable<Coordinator>  $coordinators
     */
    public static function roadblockCandidates(iterable $mentors, iterable $coordinators): array
    {
        $candidates = [];

        foreach ($mentors as $mentor) {
            $candidates[] = ['key' => "mentor-{$mentor->mentor_id}", 'type' => 'Mentor', 'person' => $mentor, 'workload' => fn () => $mentor->active_cases_count];
        }

        foreach ($coordinators as $coordinator) {
            $candidates[] = ['key' => "coordinator-{$coordinator->coordinator_id}", 'type' => 'Coordinator', 'person' => $coordinator, 'workload' => fn () => $coordinator->active_cases_count];
        }

        return $candidates;
    }

    /** @param  iterable<Coordinator>  $coordinators */
    public static function forStartup(Startup $startup, iterable $coordinators): Collection
    {
        $openRoadblocks = $startup->roadblocks()
            ->whereIn('status', ['Pending', ...Roadblock::ACTIVE_STATUSES])
            ->get();

        $needs = self::needs([
            [$startup->industry_sector, self::STARTUP_INDUSTRY_WEIGHT],
            ...$openRoadblocks->map(fn (Roadblock $roadblock) => [$roadblock->display_category, self::OPEN_ROADBLOCK_WEIGHT])->all(),
        ]);

        return self::rank($needs, self::startupCandidates($coordinators));
    }

    /** @param  iterable<Coordinator>  $coordinators */
    public static function startupCandidates(iterable $coordinators): array
    {
        $candidates = [];

        foreach ($coordinators as $coordinator) {
            $candidates[] = ['key' => "coordinator-{$coordinator->coordinator_id}", 'type' => 'Coordinator', 'person' => $coordinator, 'workload' => fn () => $coordinator->active_startups_count];
        }

        return $candidates;
    }

    /**
     * @param  array<int, array{0: ?string, 1: int}>  $weighted
     * @return array<string, array{label: string, weight: int}>
     */
    private static function needs(array $weighted): array
    {
        $needs = [];

        foreach ($weighted as [$label, $weight]) {
            $key = FocusTags::normalize($label);

            // A need listed twice counts once, at its highest weight.
            if ($key !== '' && $weight > ($needs[$key]['weight'] ?? 0)) {
                $needs[$key] = ['label' => $label, 'weight' => $weight];
            }
        }

        return $needs;
    }

    private static function rank(array $needs, array $candidates): Collection
    {
        return collect($candidates)
            ->map(function (array $candidate) use ($needs) {
                $tags = array_map(FocusTags::normalize(...), $candidate['person']->focus_tags ?? []);
                $matched = array_intersect_key($needs, array_flip($tags));

                if ($matched === []) {
                    return null;
                }

                return [
                    'key' => $candidate['key'],
                    'name' => $candidate['person']->display_name,
                    'type' => $candidate['type'],
                    'label' => count($matched) === count($needs) ? 'Full match' : 'Partial match',
                    'matched' => array_column($matched, 'label'),
                    'reason' => '',
                    'workload' => ($candidate['workload'])(),
                    'score' => array_sum(array_column($matched, 'weight')),
                ];
            })
            ->filter()
            ->sortBy([['score', 'desc'], ['workload', 'asc'], ['name', 'asc']])
            ->values();
    }
}
