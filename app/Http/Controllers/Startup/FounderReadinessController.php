<?php

namespace App\Http\Controllers\Startup;

use App\Http\Controllers\Controller;
use App\Models\ReadinessLevelAssessment;
use App\Support\ReadinessRubric;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FounderReadinessController extends Controller
{
    /**
     * Founders only ever see their own Pre/Post-Assessment readiness
     * scores — Active-Assessment uses a different document-based flow
     * (Documents 6/7/8), and Venture Exit isn't a "readiness" concept.
     */
    protected const STAGES = ['Pre-Assessment', 'Post-Assessment'];

    public function index(Request $request): View
    {
        $startup = auth()->user()->startup;

        $stage = $request->query('stage');
        $stage = in_array($stage, self::STAGES, true) ? $stage : self::STAGES[0];

        // Red dots per stage: each stage (Pre/Post) is "seen" separately, so
        // opening Pre-Assessment doesn't clear a new Post-Assessment result -
        // the stage switcher keeps a dot on whichever stage still has news.
        $user = auth()->user();
        $newStages = [];
        if ($startup) {
            foreach (self::STAGES as $s) {
                $updatedAt = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
                    ->where('stage', $s)
                    ->whereNotNull('overall_score')
                    ->value('updated_at');
                if ($updatedAt && \Illuminate\Support\Carbon::parse($updatedAt)->gt(self::stageSeenAt($user, $s))) {
                    $newStages[] = $s;
                }
            }
        }
        $user->markModuleSeen(self::stageKey($stage));
        // The sidebar dot clears once no stage has anything unseen left.
        if (array_values(array_diff($newStages, [$stage])) === []) {
            $user->markFounderModuleVisited('founder_readiness', 'startup.readiness.index');
        }

        $assessment = $startup
            ? ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
                ->where('stage', $stage)
                ->first()
            : null;

        return view('startup.readiness.index', [
            'startup' => $startup,
            'stage' => $stage,
            'stages' => self::STAGES,
            'assessment' => $assessment,
            'meta' => ReadinessRubric::meta($stage),
            'overallLabel' => ReadinessRubric::overallLabel($assessment->overall_score ?? null),
            'newStages' => $newStages,
        ]);
    }

    public static function stageKey(string $stage): string
    {
        return 'founder_readiness_'.($stage === 'Post-Assessment' ? 'post' : 'pre');
    }

    /** Last time this stage was opened (falls back to the old whole-page stamp). */
    public static function stageSeenAt(\App\Models\User $user, string $stage): \Illuminate\Support\Carbon
    {
        $seen = $user->module_seen_at ?? [];
        $key = self::stageKey($stage);

        return isset($seen[$key]) ? $user->moduleSeenAt($key) : $user->moduleSeenAt('founder_readiness');
    }
}
