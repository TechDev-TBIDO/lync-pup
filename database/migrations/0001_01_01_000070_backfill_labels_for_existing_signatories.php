<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A signatory with no label is now "no signatory": its name/position
     * boxes are locked and emptied on save. Records saved before labels
     * existed have names but blank labels, so give those signatories their
     * usual caption back — otherwise the next save would wipe their names.
     * Signatories with no name and no position are left without a label.
     */
    public function up(): void
    {
        $defaults = [
            'prepared_by' => 'Prepared By:',
            'trl_noted_by' => 'Noted By:',
            'approved_by' => 'Approved by:',
        ];
        foreach (['mrl', 'tmrl', 'srl'] as $type) {
            $defaults["{$type}_evaluated_by"] = 'Evaluated by:';
            $defaults["{$type}_reviewed_by"] = 'Reviewed by:';
            $defaults["{$type}_noted_by"] = 'Noted by:';
        }

        foreach ($defaults as $prefix => $caption) {
            DB::table('readiness_level_assessments')
                ->where(fn ($q) => $q->whereNull("{$prefix}_label")->orWhere("{$prefix}_label", ''))
                ->where(fn ($q) => $q->where(fn ($n) => $n->whereNotNull($prefix)->where($prefix, '!=', ''))
                    ->orWhere(fn ($n) => $n->whereNotNull("{$prefix}_position")->where("{$prefix}_position", '!=', '')))
                ->update(["{$prefix}_label" => $caption]);
        }

        $filled = fn ($value) => trim((string) $value) !== '';
        $documentRoles = [
            7 => ['prepared_by' => 'Prepared By:', 'noted_by' => 'Noted By:'],
            8 => ['validated_by' => 'Validated By:', 'noted_by' => 'Noted By:', 'approved_by' => 'Approved By:'],
            13 => ['evaluated_by' => 'Evaluated by:', 'reviewed_by' => 'Reviewed by:', 'noted_by' => 'Noted by:'],
        ];

        DB::table('assessment_documents')
            ->whereIn('document_number', [6, 7, 8, 13])
            ->orderBy('assessment_document_id')
            ->each(function ($row) use ($documentRoles, $filled) {
                $data = json_decode($row->data ?? '', true);
                if (! is_array($data)) {
                    return;
                }

                $changed = false;
                $setLabel = function (string $key, string $caption, bool $hasPerson) use (&$data, &$changed, $filled) {
                    if ($hasPerson && ! $filled($data[$key] ?? '')) {
                        $data[$key] = $caption;
                        $changed = true;
                    }
                };

                if ((int) $row->document_number === 6) {
                    $hasPrepared = collect($data['prepared_by'] ?? [])
                        ->contains(fn ($p) => is_array($p) && ($filled($p['name'] ?? '') || $filled($p['position'] ?? '')));
                    $setLabel('prepared_by_label', 'Prepared By:', $hasPrepared);
                    $setLabel('noted_by_label', 'Noted By:', $filled($data['noted_by'] ?? '') || $filled($data['noted_by_position'] ?? ''));
                } else {
                    foreach ($documentRoles[(int) $row->document_number] as $role => $caption) {
                        $hasPerson = $filled($data["{$role}_name"] ?? '') || $filled($data["{$role}_position"] ?? '')
                            || ($role === 'validated_by' && ($filled($data['validated_by_contact'] ?? '') || $filled($data['validated_by_date'] ?? '')));
                        $setLabel("{$role}_label", $caption, $hasPerson);
                    }
                }

                if ($changed) {
                    DB::table('assessment_documents')
                        ->where('assessment_document_id', $row->assessment_document_id)
                        ->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Not reversed: the restored captions are ordinary editable labels.
    }
};
