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

        // Clears this page's founder sidebar red dot (see AppServiceProvider).
        auth()->user()?->markModuleSeen('founder_readiness', now());

        $stage = $request->query('stage');
        $stage = in_array($stage, self::STAGES, true) ? $stage : self::STAGES[0];

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
        ]);
    }
}
