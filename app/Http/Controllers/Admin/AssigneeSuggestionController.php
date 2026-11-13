<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Services\AiAssigneeMatcher;
use Illuminate\Http\JsonResponse;

/**
 * Loaded when an assign modal opens rather than with the page, since these
 * pages render many modals and each AI answer can take a few seconds.
 */
class AssigneeSuggestionController extends Controller
{
    public function roadblock(Roadblock $roadblock, AiAssigneeMatcher $matcher): JsonResponse
    {
        $roadblock->loadMissing('startup');

        $result = $matcher->forRoadblock(
            $roadblock,
            Mentor::orderBy('mentor_id')->get(),
            Coordinator::with(['assignments' => fn ($q) => $q->where('assignment_status', 'Active')])->orderBy('coordinator_id')->get(),
        );

        return response()->json(['source' => $result['source'], 'matches' => $result['matches']->take(3)->values()]);
    }

    public function startup(Startup $startup, AiAssigneeMatcher $matcher): JsonResponse
    {
        $current = $startup->activeCoordinatorAssignment?->coordinator_id;

        $result = $matcher->forStartup(
            $startup,
            Coordinator::with(['assignments' => fn ($q) => $q->where('assignment_status', 'Active')])
                ->when($current, fn ($q) => $q->where('coordinator_id', '!=', $current))
                ->orderBy('coordinator_id')
                ->get(),
        );

        return response()->json(['source' => $result['source'], 'matches' => $result['matches']->values()]);
    }
}
