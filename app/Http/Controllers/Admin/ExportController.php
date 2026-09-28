<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentDocument;
use App\Models\ReadinessLevelAssessment;
use App\Models\SavedReport;
use App\Models\Startup;
use App\Services\Exports\WordDocumentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ExportController extends Controller
{
    /**
     * Every document the checklist always shows, regardless of whether the
     * startup actually has data for it yet (explicit product decision: list
     * all 13 always). Keyed by the real PUP-TBIDO form number.
     */
    private const DOCUMENTS = [
        1 => ['label' => 'Startup Information Sheet', 'form_no' => '001'],
        2 => ['label' => 'Pre-Assessment TRL', 'form_no' => '002'],
        3 => ['label' => 'Pre-Assessment MRL', 'form_no' => '003'],
        4 => ['label' => 'Pre-Assessment TMRL', 'form_no' => '004'],
        5 => ['label' => 'Pre-Assessment SRL', 'form_no' => '005'],
        6 => ['label' => 'Startup Growth Strategy', 'form_no' => '006'],
        7 => ['label' => 'Weekly Check-Ins', 'form_no' => '007'],
        8 => ['label' => 'Prototype Validation Form', 'form_no' => '008'],
        9 => ['label' => 'Post-Assessment TRL', 'form_no' => '009'],
        10 => ['label' => 'Post-Assessment MRL', 'form_no' => '010'],
        11 => ['label' => 'Post-Assessment TMRL', 'form_no' => '011'],
        12 => ['label' => 'Post-Assessment SRL', 'form_no' => '012'],
        13 => ['label' => 'Startup Exit Form', 'form_no' => '013'],
    ];

    protected WordDocumentExporter $wordExporter;

    public function __construct(WordDocumentExporter $wordExporter)
    {
        $this->wordExporter = $wordExporter;
    }

    /**
     * Returns the checklist (document numbers + labels) for the "Select
     * Documents" step — used by the Export Document modal.
     */
    public function documents(): JsonResponse
    {
        return response()->json([
            'documents' => collect(self::DOCUMENTS)->map(fn($doc, $num) => [
                'number' => $num,
                'label' => $doc['label'],
                'form_no' => $doc['form_no'],
            ])->values(),
        ]);
    }

    /**
     * Which of the 13 documents actually have real data yet for this
     * startup — powers the "not-started documents are unselectable" rule
     * in the Export Document modal's checklist. A document number missing
     * from the returned list means nothing has been filled in for it, so
     * there'd be nothing meaningful to export.
     */
    public function documentStatus(Startup $startup): JsonResponse
    {
        $available = [];

        if ($startup->informationSheet) {
            $available[] = 1;
        }

        $rubricMap = [
            2 => ['TRL', 'Pre-Assessment'],
            3 => ['MRL', 'Pre-Assessment'],
            4 => ['TMRL', 'Pre-Assessment'],
            5 => ['SRL', 'Pre-Assessment'],
            9 => ['TRL', 'Post-Assessment'],
            10 => ['MRL', 'Post-Assessment'],
            11 => ['TMRL', 'Post-Assessment'],
            12 => ['SRL', 'Post-Assessment'],
        ];

        $assessmentsByStage = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->whereIn('stage', ['Pre-Assessment', 'Post-Assessment'])
            ->get()
            ->keyBy('stage');

        foreach ($rubricMap as $num => [$type, $stage]) {
            $assessment = $assessmentsByStage->get($stage);
            if ($assessment && $assessment->scoreFor($type) !== null) {
                $available[] = $num;
            }
        }

        $documents = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->whereIn('document_number', [6, 7, 8, 13])
            ->get();

        foreach ($documents as $document) {
            if (filled($document->data)) {
                $available[] = $document->document_number;
            }
        }

        sort($available);

        return response()->json(['available' => array_values($available)]);
    }

    /**
     * Generates the actual PDF/ZIP file(s) for the selected startup +
     * documents + format, and stores them to the public disk. Nothing is
     * written to `saved_reports` yet — that only happens if the admin
     * explicitly clicks "Save to Reports" (see save() below). Downloading
     * straight from the "Export Completed" screen works regardless, since
     * the file already physically exists on disk at this point.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'startup_id' => ['required', 'integer', 'exists:startups,startup_id'],
            'document_numbers' => ['required', 'array', 'min:1'],
            'document_numbers.*' => ['integer', Rule::in(array_keys(self::DOCUMENTS))],
            'format' => ['required', Rule::in(['PDF Bundle', 'ZIP Archive', 'Individual PDFs'])],
            'file_name' => ['required', 'string', 'max:150'],
        ]);

        $startup = Startup::findOrFail($validated['startup_id']);

        $documentNumbers = collect($validated['document_numbers'])
            ->map(fn($n) => (int) $n)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $format = $validated['format'];
        $baseName = $this->sanitizeFileName($validated['file_name']);
        $batch = (string) Str::uuid();
        $dir = "exports/{$startup->startup_id}/{$batch}";

        // "PDF Bundle" merges every selected document into ONE PDF by
        // concatenating their HTML before a single DomPDF pass. A
        // document rendered from a real Word master (see
        // WordDocumentExporter) isn't HTML at all by the time it's a
        // PDF, so it can't be folded into that same pass - there's no
        // merge step yet that stitches an already-rendered PDF into
        // another one. Blocked here rather than silently dropping the
        // Word-backed document or silently falling back to the old
        // Blade recreation for it.
        $wordBacked = array_values(array_filter($documentNumbers, fn($n) => $this->wordExporter->hasTemplate($n)));

        // A Word-backed document is a filled .docx now, not a PDF at all
        // (see WordDocumentExporter::renderDocument1() - PDF conversion via
        // LibreOffice was dropped after it proved unreliable on Macy's
        // Windows setup). "PDF Bundle" promises one merged PDF, which a
        // .docx can't be part of, even alone - so it's blocked outright
        // whenever a Word-backed document is selected, not just when it's
        // combined with others.
        if ($format === 'PDF Bundle' && count($wordBacked) > 0) {
            $label = self::DOCUMENTS[$wordBacked[0]]['label'];

            throw ValidationException::withMessages([
                'format' => [
                    "\"PDF Bundle\" can't include \"{$label}\" — it now exports as a real .docx from the "
                        . 'PUP-TBIDO Word master, not a PDF, so it has nothing to merge into a PDF bundle. '
                        . 'Choose "Individual PDFs" or "ZIP Archive" instead.',
                ],
            ]);
        }

        $files = match ($format) {
            'PDF Bundle' => [$this->makeBundleFile($dir, $baseName, $documentNumbers, $startup)],
            'Individual PDFs' => $this->makeIndividualFiles($dir, $baseName, $documentNumbers, $startup),
            'ZIP Archive' => [$this->makeZipFile($dir, $baseName, $documentNumbers, $startup)],
        };

        return response()->json([
            'export_batch' => $batch,
            'startup_id' => $startup->startup_id,
            'format' => $format,
            'documents_included' => count($documentNumbers),
            'documents_total' => count(self::DOCUMENTS),
            'generated_at' => now()->toIso8601String(),
            'files' => $files,
        ]);
    }

    /**
     * Catalogs already-generated file(s) into `saved_reports` so they show
     * up under the Reports tab. The client echoes back exactly the file
     * list it received from generate() — each entry is re-validated here
     * (path must sit inside this startup's own batch folder, and the file
     * must actually exist) before a row is written.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'startup_id' => ['required', 'integer', 'exists:startups,startup_id'],
            'export_batch' => ['required', 'uuid'],
            'files' => ['required', 'array', 'min:1'],
            'files.*.file_name' => ['required', 'string'],
            'files.*.file_path' => ['required', 'string'],
            'files.*.format' => ['required', Rule::in(['PDF Bundle', 'ZIP Archive', 'Individual PDFs'])],
            'files.*.document_numbers' => ['required', 'array'],
            'files.*.document_numbers.*' => ['integer'],
            'files.*.page_count' => ['nullable', 'integer'],
            'files.*.file_size_bytes' => ['required', 'integer'],
        ]);

        $startup = Startup::findOrFail($validated['startup_id']);
        $expectedPrefix = "exports/{$startup->startup_id}/{$validated['export_batch']}/";

        $saved = [];
        foreach ($validated['files'] as $file) {
            if (
                ! str_starts_with($file['file_path'], $expectedPrefix)
                || ! Storage::disk('public')->exists($file['file_path'])
            ) {
                continue;
            }

            $saved[] = SavedReport::create([
                'startup_id' => $startup->startup_id,
                'file_name' => $file['file_name'],
                'file_path' => $file['file_path'],
                'export_batch' => $validated['export_batch'],
                'format' => $file['format'],
                'document_numbers' => $file['document_numbers'],
                'page_count' => $file['page_count'] ?? 0,
                'file_size_bytes' => $file['file_size_bytes'],
                'generated_by' => $request->user()?->name,
            ]);
        }

        return response()->json([
            'saved_reports' => collect($saved)->map(fn(SavedReport $r) => [
                'saved_report_id' => $r->saved_report_id,
                'file_name' => $r->file_name,
                'file_size_label' => $r->file_size_label,
                'download_url' => route('admin.exports.download', $r),
            ])->values(),
        ]);
    }

    public function download(SavedReport $savedReport)
    {
        abort_unless(Storage::disk('public')->exists($savedReport->file_path), 404);

        return Storage::disk('public')->download($savedReport->file_path, $savedReport->file_name);
    }

    public function destroy(SavedReport $savedReport)
    {
        Storage::disk('public')->delete($savedReport->file_path);
        $savedReport->delete();

        return back()->with('status', 'Report file deleted.');
    }

    /**
     * Dev/QA helper: streams a single document's PDF straight into the
     * browser tab (inline, not a download) so layout tweaks can be
     * checked with just a page refresh - no need to go through
     * Generate -> Save -> Download each time.
     *
     * Visit /admin/exports/preview to preview document 1 for the first
     * startup in the database, or /admin/exports/preview/{startup} for a
     * specific one. Add ?document=N to preview a different document
     * number. Document 1 renders from the real Word master
     * (WordDocumentExporter); every other number still goes through the
     * DomPDF/Blade recreation until real masters exist for them too.
     */
    public function preview(Request $request, ?Startup $startup = null)
    {
        $startup ??= Startup::query()->orderBy('startup_id')->first();

        abort_unless($startup, 404, 'No startups found to preview.');

        $documentNumber = (int) $request->query('document', 1);
        $binary = $this->renderDocumentPdf($documentNumber, $startup);
        $extension = $this->extensionFor($documentNumber);
        $contentType = $extension === 'docx'
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/pdf';

        // A .docx can't render inline in a browser tab the way a PDF does -
        // "inline" here just means the browser picks its own default (most
        // will still download it), so Macy opens it in Word herself.
        return response($binary, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "inline; filename=\"preview-doc{$documentNumber}.{$extension}\"",
        ]);
    }

    /**
     * The file extension a document actually renders as: "docx" for a
     * Word-backed document (see WordDocumentExporter::outputExtension()),
     * "pdf" for everything still on the DomPDF/Blade pipeline.
     */
    protected function extensionFor(int $documentNumber): string
    {
        return $this->wordExporter->hasTemplate($documentNumber)
            ? $this->wordExporter->outputExtension($documentNumber)
            : 'pdf';
    }

    // ============ document rendering ============

    /**
     * Renders one document to a finished PDF binary, picking the real
     * Word master (WordDocumentExporter) when one exists for this
     * document number, and falling back to the DomPDF/Blade recreation
     * otherwise. Every caller that needs a single document's PDF bytes
     * should go through this rather than DomPDF directly, so document 1
     * (and any future document that gets a real master) automatically
     * benefits everywhere: preview, Individual PDFs, ZIP Archive, and a
     * single-document PDF Bundle.
     */
    protected function renderDocumentPdf(int $documentNumber, Startup $startup): string
    {
        $wordPdf = $this->wordExporter->render($documentNumber, $startup);

        if ($wordPdf !== null) {
            return $wordPdf;
        }

        $html = $this->renderDocumentContent($documentNumber, $startup);
        $pdf = Pdf::loadView('admin.exports.bundle', ['sections' => [$html]])->setPaper($this->paperSizeFor($documentNumber));

        return $pdf->output();
    }

    protected function renderDocumentContent(int $documentNumber, Startup $startup): string
    {
        if ($documentNumber === 1) {
            return view('admin.exports._content-1', ['startup' => $startup])->render();
        }

        if (in_array($documentNumber, [2, 3, 4, 5, 9, 10, 11, 12], true)) {
            return $this->renderRubricContent($documentNumber, $startup);
        }

        if (in_array($documentNumber, [6, 7, 8], true)) {
            $document = AssessmentDocument::where('startup_id', $startup->startup_id)
                ->where('stage', 'Active-Assessment')
                ->where('document_number', $documentNumber)
                ->first();

            return view("admin.exports._content-{$documentNumber}", compact('startup', 'document'))->render();
        }

        // Document 13: Startup Exit Form.
        $document = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Venture Exit')
            ->where('document_number', 13)
            ->first();

        return view('admin.exports._content-13', compact('startup', 'document'))->render();
    }

    protected function renderRubricContent(int $documentNumber, Startup $startup): string
    {
        [$type, $stage] = match ($documentNumber) {
            2 => ['TRL', 'Pre-Assessment'],
            3 => ['MRL', 'Pre-Assessment'],
            4 => ['TMRL', 'Pre-Assessment'],
            5 => ['SRL', 'Pre-Assessment'],
            9 => ['TRL', 'Post-Assessment'],
            10 => ['MRL', 'Post-Assessment'],
            11 => ['TMRL', 'Post-Assessment'],
            12 => ['SRL', 'Post-Assessment'],
        };

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', $stage)
            ->first();

        return view('admin.exports._rubric-content', compact('startup', 'type', 'stage', 'assessment'))->render();
    }

    // ============ file builders ============

    /**
     * Every document prints on Legal paper (8.5in x 14in - DomPDF's
     * built-in "legal" preset, 612x1008pt) per Macy's spec. Only used by
     * the DomPDF/Blade fallback path now - a document rendered from a
     * real Word master takes its page size from that master's own
     * @page section instead.
     */
    protected function paperSizeFor(int $documentNumber): string
    {
        return 'legal';
    }

    /**
     * @param  array<int, int>  $documentNumbers
     */
    protected function makeBundleFile(string $dir, string $baseName, array $documentNumbers, Startup $startup): array
    {
        // generate() already blocks "PDF Bundle" outright whenever any
        // selected document is Word-backed (it's a .docx now, not a PDF -
        // nothing to merge into a bundle), so every document reaching this
        // method is on the DomPDF/Blade pipeline. Defensive check instead
        // of silently mis-handling it if that guard is ever changed.
        foreach ($documentNumbers as $num) {
            if ($this->wordExporter->hasTemplate($num)) {
                throw new \RuntimeException(
                    "Document {$num} is Word-backed and can't be part of a PDF Bundle - this should have "
                        . 'been caught by the validation in generate() before reaching here.'
                );
            }
        }

        $sections = array_map(fn($num) => $this->renderDocumentContent($num, $startup), $documentNumbers);
        $pdf = Pdf::loadView('admin.exports.bundle', ['sections' => $sections])->setPaper('legal');
        $binary = $pdf->output();

        $fileName = "{$baseName}.pdf";
        $path = "{$dir}/{$fileName}";
        Storage::disk('public')->put($path, $binary);

        return [
            'file_name' => $fileName,
            'file_path' => $path,
            'format' => 'PDF Bundle',
            'document_numbers' => $documentNumbers,
            'page_count' => $this->pageCount($binary),
            'file_size_bytes' => strlen($binary),
            'file_size_label' => $this->sizeLabel(strlen($binary)),
            'download_url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * @param  array<int, int>  $documentNumbers
     */
    protected function makeIndividualFiles(string $dir, string $baseName, array $documentNumbers, Startup $startup): array
    {
        $files = [];

        foreach ($documentNumbers as $num) {
            $binary = $this->renderDocumentPdf($num, $startup);
            $extension = $this->extensionFor($num);

            $label = self::DOCUMENTS[$num]['label'];
            $fileName = $this->sanitizeFileName("{$startup->company_name} - {$label}") . ".{$extension}";
            $path = "{$dir}/{$fileName}";
            Storage::disk('public')->put($path, $binary);

            $files[] = [
                'file_name' => $fileName,
                'file_path' => $path,
                'format' => 'Individual PDFs',
                'document_numbers' => [$num],
                // pageCount() looks for PDF page objects in the bytes - not
                // meaningful for a .docx, so it's skipped there rather than
                // reporting a wrong number.
                'page_count' => $extension === 'pdf' ? $this->pageCount($binary) : null,
                'file_size_bytes' => strlen($binary),
                'file_size_label' => $this->sizeLabel(strlen($binary)),
                'download_url' => Storage::disk('public')->url($path),
            ];
        }

        return $files;
    }

    /**
     * @param  array<int, int>  $documentNumbers
     */
    protected function makeZipFile(string $dir, string $baseName, array $documentNumbers, Startup $startup): array
    {
        $fileName = "{$baseName}.zip";
        $path = "{$dir}/{$fileName}";

        // ZipArchive::close() doesn't just write the file in place once
        // anything's been added — it stages the new archive contents in a
        // temp file right next to the target path and renames it over the
        // original. That rename dance needs real local-filesystem
        // semantics, which storage/app/public doesn't have on Azure App
        // Service (it's the Azure Files SMB share behind /home, kept for
        // persistence across restarts) — close() fails there with
        // "Failure to create temporary file: No such file or directory"
        // even though ZipArchive::open() on the very same path succeeded
        // moments earlier, and even though a PLAIN write to that same
        // share (Storage::put(), or WordDocumentExporter's own temp
        // .docx files, written and read back directly) works fine. Only
        // libzip's internal close-time rename trips on it. Building the
        // archive on PHP's own local temp path sidesteps that entirely;
        // the finished bytes are then handed to the Storage facade like
        // every other export file already is, instead of trusting
        // ZipArchive to have written straight into public storage itself.
        $localPath = sys_get_temp_dir() . '/' . uniqid('export-zip-', true) . '.zip';

        $zip = new ZipArchive();
        $opened = $zip->open($localPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new \RuntimeException("Could not create the ZIP archive (error code {$opened}).");
        }

        $totalPages = 0;
        foreach ($documentNumbers as $num) {
            $binary = $this->renderDocumentPdf($num, $startup);
            $extension = $this->extensionFor($num);
            $totalPages += $extension === 'pdf' ? ($this->pageCount($binary) ?? 0) : 0;

            $label = self::DOCUMENTS[$num]['label'];
            $individualFileName = $this->sanitizeFileName("{$startup->company_name} - {$label}") . ".{$extension}";

            $zip->addFromString($individualFileName, $binary);
        }

        $zip->close();

        $binary = file_get_contents($localPath);
        @unlink($localPath);

        Storage::disk('public')->put($path, $binary);
        $size = strlen($binary);

        return [
            'file_name' => $fileName,
            'file_path' => $path,
            'format' => 'ZIP Archive',
            'document_numbers' => $documentNumbers,
            'page_count' => $totalPages ?: null,
            'file_size_bytes' => $size,
            'file_size_label' => $this->sizeLabel($size),
            'download_url' => Storage::disk('public')->url($path),
        ];
    }

    // ============ helpers ============

    /**
     * Counts pages by counting "/Type /Page" object dictionaries in the
     * raw PDF bytes (excluding "/Type /Pages", the page-tree node, via the
     * trailing "s"). Works the same way regardless of whether the PDF
     * came from DomPDF or from LibreOffice's Word-to-PDF conversion, so
     * this replaced the old DomPDF-canvas-specific pageCount() - there's
     * no DomPDF object at all for a document rendered from a Word master.
     * Not bulletproof against every possible PDF producer's internal
     * structure, but solid for the two producers this app actually uses.
     */
    protected function pageCount(string $pdfBinary): ?int
    {
        try {
            $count = preg_match_all('/\/Type\s*\/Page(?!s)/', $pdfBinary);

            return $count > 0 ? $count : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function sanitizeFileName(string $name): string
    {
        $name = trim(preg_replace('/[\/\\\\:*?"<>|]+/', '-', $name));

        return $name !== '' ? $name : 'Export';
    }

    protected function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round(max($bytes, 1) / 1024, 1) . ' KB';
    }
}
