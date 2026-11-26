<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentMeeting;
use App\Models\Cohort;
use App\Models\Roadblock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Archive — one place for everything the admin side has archived:
 *
 *   - Cohorts that were archived ("Archive/End Cohort"), with Unarchive.
 *   - Roadblocks closed as Resolved or Failed (Roadblock Management's own
 *     Archive), with Recover for a Resolved one (same action that page has).
 *   - Assessment meetings closed as Resolved or Failed (Incubatee > Meetings
 *     > Archive), with Recover for a Resolved one.
 *
 * Every list shares the same filters: a search box, a status filter and a
 * newest/oldest sort; roadblocks and meetings also follow the sidebar's
 * selected cohort like the rest of the admin pages. Nothing here deletes
 * anything - un-archiving reuses the existing actions on each record.
 */
class ArchiveController extends Controller
{
    public const TYPES = ['cohorts' => 'Cohorts', 'roadblocks' => 'Roadblocks', 'meetings' => 'Assessment Meetings'];

    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : 'cohorts';
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'newest';

        $cohortNumber = ($id = session('selected_cohort_id')) ? Cohort::find($id)?->number : null;

        $cohorts = Cohort::query()
            ->where('status', 'Inactive')
            ->withCount('startups')
            ->get();

        $roadblocks = Roadblock::query()
            ->with('startup')
            ->whereIn('status', ['Resolved', 'Failed'])
            ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
            ->get();

        $meetings = AssessmentMeeting::query()
            ->with('startup')
            ->whereIn('status', [AssessmentMeeting::STATUS_RESOLVED, AssessmentMeeting::STATUS_FAILED])
            ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
            ->get();

        // One row shape for every type, so the page renders a single table.
        $rows = [
            'cohorts' => $cohorts->map(fn (Cohort $c) => [
                'id' => $c->cohort_id,
                'name' => $c->display_label,
                'detail' => trim(($c->start_date?->format('M j, Y') ?? '—').' – '.($c->end_date?->format('M j, Y') ?? '—')).' · '.$c->startups_count.' '.str('startup')->plural($c->startups_count),
                'status' => 'Archived',
                'date' => $c->updated_at,
                'model' => $c,
            ]),
            'roadblocks' => $roadblocks->map(fn (Roadblock $r) => [
                'id' => $r->roadblock_id,
                'name' => $r->startup?->company_name ?? 'Deleted startup',
                'detail' => $r->display_category,
                'status' => $r->status,
                'date' => $r->status === 'Resolved' ? ($r->resolved_at ?? $r->updated_at) : ($r->failed_at ?? $r->updated_at),
                'model' => $r,
            ]),
            'meetings' => $meetings->map(fn (AssessmentMeeting $m) => [
                'id' => $m->getKey(),
                'name' => $m->startup?->company_name ?? 'Deleted startup',
                'detail' => trim(($m->stage ?? 'Meeting').' · '.($m->meeting_date?->format('M j, Y') ?? '')),
                'status' => $m->status,
                'date' => $m->status === AssessmentMeeting::STATUS_RESOLVED ? ($m->resolved_at ?? $m->updated_at) : ($m->failed_at ?? $m->updated_at),
                'model' => $m,
            ]),
        ];

        $counts = collect($rows)->map->count()->all();

        $statuses = $rows[$type]->pluck('status')->unique()->sort()->values()->all();
        if ($status !== 'all' && ! in_array($status, $statuses, true)) {
            $status = 'all';
        }

        $list = $this->filter($rows[$type], $search, $status, $sort);

        return view('admin.archive.index', [
            'types' => self::TYPES,
            'type' => $type,
            'search' => $search,
            'status' => $status,
            'statuses' => $statuses,
            'sort' => $sort,
            'rows' => $list,
            'counts' => $counts,
        ]);
    }

    private function filter(Collection $rows, string $search, string $status, string $sort): Collection
    {
        $needle = mb_strtolower($search);

        $rows = $rows
            ->when($needle !== '', fn ($c) => $c->filter(fn ($row) => str_contains(mb_strtolower($row['name'].' '.$row['detail']), $needle)))
            ->when($status !== 'all', fn ($c) => $c->where('status', $status));

        $rows = $rows->sortBy(fn ($row) => $row['date'] instanceof Carbon ? $row['date']->getTimestamp() : 0);

        return ($sort === 'newest' ? $rows->reverse() : $rows)->values();
    }
}
