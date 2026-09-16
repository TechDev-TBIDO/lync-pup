<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCoordinatorRequest;
use App\Models\Coordinator;
use App\Models\Startup;
use App\Models\VersionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoordinatorAssignmentController extends Controller
{
    public function store(AssignCoordinatorRequest $request, Startup $startup): RedirectResponse
    {
        DB::transaction(function () use ($request, $startup) {
            $startup->coordinatorAssignments()->where('assignment_status', 'Active')
                ->update(['assignment_status' => 'Completed']);

            $startup->coordinatorAssignments()->create([
                'coordinator_id' => $request->validated('coordinator_id'),
                'assigned_date' => now(),
                'assignment_status' => 'Active',
            ]);

            $coordinator = Coordinator::findOrFail($request->validated('coordinator_id'));
            $coordinator->increment('assigned_startups_count');

            VersionHistory::record($startup, 'Startup Profile', 'assign_coordinator', "{$coordinator->name} → {$startup->company_name}");

            // The Information Sheet has its own "Portfolio Manager" field
            // (see admin/information-sheets/show.blade.php's
            // $selectField('portfolio_manager', ...)) — a separate column,
            // not read from coordinatorAssignments. Without this, assigning
            // someone here left that field blank until an admin separately
            // reopened and resaved the sheet, even though the assignment
            // this button makes IS the actual answer to "who is the
            // portfolio manager". Only touches an already-existing sheet —
            // a startup can be assigned a coordinator before it has even
            // started one.
            $startup->informationSheet?->update(['portfolio_manager' => $coordinator->name]);
        });

        // The Startup Profile card grid's 3-dot "Edit Coordinator" reuses
        // this same component/route (see coordinator-assign-modal.blade.php)
        // but wants to land back on that filtered/paginated list, not always
        // on the show page. Restricted to a same-origin relative path —
        // this is user-submitted input, so it must never redirect off-app.
        $returnUrl = $request->input('return_url');
        $redirectTo = ($returnUrl && Str::startsWith($returnUrl, url('/')))
            ? $returnUrl
            : route('admin.startups.show', $startup);

        return redirect($redirectTo)
            ->with('status', 'Portfolio Coordinator assigned successfully.');
    }
}