<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Signatory names/positions used to arrive pre-filled with fixed TBIDO
     * defaults, and those defaults got saved into existing rows. Signatories
     * are now placeholder-only, so blank out any stored value that is still
     * EXACTLY one of those old defaults. Anything an admin actually typed
     * (i.e. anything that differs from a default) is left untouched.
     */
    public function up(): void
    {
        $evaluatedPositions = [
            'Portfolio Coordinator, TBIDO',
            "Portfolio Coordinator, TBIDO\nProject Technical Assistant II, DOST HEIRIT",
        ];
        $notedPosition = ["Director, TBIDO\nProject Leader, DOST HEIRIT"];

        $columns = [
            'approved_by' => ['DR. PHILIP P. ERMITA , PIE, PDQM, ASEAN ENG.', 'DR. PHILIP P. ERMITA, PIE, PDQM, ASEAN ENG.'],
            'approved_by_position' => ["Director, Technology Business Incubation and Development Office\nProject Leader, DOST-HEIRIT"],
            'mrl_evaluated_by_position' => $evaluatedPositions,
            'tmrl_evaluated_by_position' => $evaluatedPositions,
            'srl_evaluated_by_position' => $evaluatedPositions,
            'mrl_reviewed_by_position' => ['Startup Development Chief, TBIDO'],
            'tmrl_reviewed_by_position' => ['Startup Development Chief, TBIDO'],
            'srl_reviewed_by_position' => ['Incubation Management Chief, TBIDO'],
            'mrl_noted_by_position' => $notedPosition,
            'tmrl_noted_by_position' => $notedPosition,
            'srl_noted_by_position' => $notedPosition,
        ];

        foreach ($columns as $column => $defaults) {
            foreach ($defaults as $default) {
                foreach ([$default, str_replace("\n", "\r\n", $default)] as $value) {
                    DB::table('readiness_level_assessments')->where($column, $value)->update([$column => null]);
                }
            }
        }

        // Document 6/7/8 and Venture Exit (13) keep their fields in one JSON column.
        $documentDefaults = [
            6 => [
                'noted_by_position' => ['Director, TBIDO'],
                'prepared_by.*.position' => ['Startup Development Chief, TBIDO', 'Incubation Management Chief, TBIDO', 'Technology Development Chief, TBIDO'],
            ],
            7 => [
                'prepared_by_position' => ['Portfolio Coordinator, TBIDO'],
                'noted_by_position' => ['Assigned Chief, TBIDO'],
            ],
            8 => [
                'noted_by_name' => ['DR. JUANCHO D. ESPINELI'],
                'noted_by_position' => ['Chief, Technology Development Section, PUP'],
                'approved_by_name' => ['DR. PHILIP P. ERMITA, PIE, PDQM, ASEAN ENG.'],
                'approved_by_position' => ["Director, Technology Business Incubation and Development Office, PUP\nProject Leader, DOST-HEIRIT"],
            ],
            13 => [
                'evaluated_by_position' => ['Portfolio Coordinator, TBIDO'],
                'reviewed_by_position' => ['Incubation Management Chief, TBIDO'],
                'noted_by_position' => ['Director, TBIDO'],
            ],
        ];

        $matches = fn ($value, array $defaults) => is_string($value)
            && in_array(str_replace("\r\n", "\n", $value), $defaults, true);

        DB::table('assessment_documents')
            ->whereIn('document_number', array_keys($documentDefaults))
            ->orderBy('assessment_document_id')
            ->each(function ($row) use ($documentDefaults, $matches) {
                $data = json_decode($row->data ?? '', true);
                if (! is_array($data)) {
                    return;
                }

                $changed = false;
                foreach ($documentDefaults[$row->document_number] as $key => $defaults) {
                    if ($key === 'prepared_by.*.position') {
                        foreach ($data['prepared_by'] ?? [] as $i => $person) {
                            if (is_array($person) && $matches($person['position'] ?? null, $defaults)) {
                                $data['prepared_by'][$i]['position'] = '';
                                $changed = true;
                            }
                        }

                        continue;
                    }

                    if ($matches($data[$key] ?? null, $defaults)) {
                        $data[$key] = '';
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('assessment_documents')->where('assessment_document_id', $row->assessment_document_id)->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: the cleared defaults are not restored.
    }
};
