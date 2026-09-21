<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VersionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages Version History log entries themselves — the one thing an admin
 * can do to an entry is rename its display label. That never touches the
 * underlying record (InformationSheet, EvaluationSchedule,
 * ReadinessLevelAssessment, AssessmentDocument) the entry describes; this
 * is a read-only activity log, not a data-restoring versioning system.
 *
 * There is intentionally no delete: Edit History is the record of who
 * changed what, so an entry can be relabelled but never removed.
 */
class VersionHistoryController extends Controller
{
    public function update(Request $request, VersionHistory $versionHistory): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:150'],
        ]);

        // Blank/whitespace-only input reverts to the auto-generated
        // timestamp label rather than saving an empty string.
        $versionHistory->update([
            'label' => trim((string) ($data['label'] ?? '')) ?: null,
        ]);

        return back()->with('status', 'Version renamed.');
    }
}
