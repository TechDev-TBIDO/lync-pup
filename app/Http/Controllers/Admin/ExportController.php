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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
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
            // A saved-but-blank form still has a non-empty data array (every
            // key present, values empty), so check for real content instead.
            $data = (array) ($document->data ?? []);
            $hasContent = (int) $document->document_number === 13
                ? \App\Support\ActiveAssessmentForms::isVentureExitFilled($data)
                : \App\Support\ActiveAssessmentForms::isDocumentFilled((int) $document->document_number, $data);
            if ($hasContent) {
                $available[] = $document->document_number;
            }
        }

        sort($available);

        return response()->json(['available' => array_values($available)]);
    }

    /**
     * Builds the requested export and sends it straight back to the admin's
     * browser as a file download. Nothing is written to storage or to the
     * database: per the client, exported documents should only end up on
     * the user's own device, not be kept in the system (they used to be
     * uploaded to R2 on every export - even ones nobody saved - and never
     * cleaned up).
     *
     * One file per request:
     *  - "ZIP Archive" / "PDF Bundle": one file covering every selected document
     *  - "Individual PDFs": exactly one document per request (the export
     *    modal requests each selected document in turn), so the server only
     *    ever holds one rendered file in memory at a time.
     */
    public function generate(Request $request): Response
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

        if ($format === 'Individual PDFs' && count($documentNumbers) !== 1) {
            throw ValidationException::withMessages([
                'document_numbers' => ['Individual files are generated one document per request.'],
            ]);
        }

        return $this->fileDownloadResponse($this->buildFile($startup, $format, $documentNumbers, $baseName));
    }

    /**
     * Renders one export file in memory (never stored). Shared by generate()
     * and by download(), which rebuilds a saved report from its recipe.
     *
     * @param  array<int, int>  $documentNumbers
     * @return array{file_name: string, extension: string, binary: string}
     */
    protected function buildFile(Startup $startup, string $format, array $documentNumbers, string $baseName): array
    {

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

        return match ($format) {
            'PDF Bundle' => $this->makeBundleFile($baseName, $documentNumbers, $startup),
            'Individual PDFs' => $this->makeIndividualFile($documentNumbers[0], $startup),
            'ZIP Archive' => $this->makeZipFile($baseName, $documentNumbers, $startup),
        };
    }

    /**
     * "Save to Reports": stores only the RECIPE of each exported file -
     * which startup, which documents, which format, and the file name the
     * admin chose - never the file itself. Opening it from the Reports tab
     * rebuilds the file on the spot (see download()), so nothing is kept
     * in storage.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'startup_id' => ['required', 'integer', 'exists:startups,startup_id'],
            'files' => ['required', 'array', 'min:1', 'max:13'],
            'files.*.file_name' => ['required', 'string', 'max:200'],
            'files.*.format' => ['required', Rule::in(['PDF Bundle', 'ZIP Archive', 'Individual PDFs'])],
            'files.*.document_numbers' => ['required', 'array', 'min:1'],
            'files.*.document_numbers.*' => ['integer', Rule::in(array_keys(self::DOCUMENTS))],
            'files.*.file_size_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $batch = (string) \Illuminate\Support\Str::uuid();

        foreach ($validated['files'] as $file) {
            SavedReport::create([
                'startup_id' => $validated['startup_id'],
                'file_name' => $this->sanitizeFileName($file['file_name']),
                // No stored file any more - empty path marks a recipe-only
                // report (rows from before this change still hold the old
                // R2 path until `php artisan exports:purge-stored` clears it).
                'file_path' => '',
                'export_batch' => $batch,
                'format' => $file['format'],
                'document_numbers' => array_values(array_unique(array_map('intval', $file['document_numbers']))),
                'page_count' => 0,
                // Size of the file when it was saved, shown in the Reports
                // list; a regenerated copy may differ if the data changed.
                'file_size_bytes' => $file['file_size_bytes'] ?? 0,
                'generated_by' => $request->user()?->name,
            ]);
        }

        return response()->json(['saved' => count($validated['files'])]);
    }

    /**
     * Reports tab "Download File": rebuilds the saved report from its recipe
     * with the startup's CURRENT data and sends it to the admin's device.
     * Works for older saved reports too (their format + document list were
     * always recorded), so their stored files can be purged safely.
     */
    public function download(SavedReport $savedReport): Response
    {
        $startup = Startup::findOrFail($savedReport->startup_id);
        $documentNumbers = collect($savedReport->document_numbers ?? [])
            ->map(fn ($n) => (int) $n)
            ->filter(fn ($n) => isset(self::DOCUMENTS[$n]))
            ->unique()->sort()->values()->all();

        abort_if($documentNumbers === [], 404, 'This saved report has no documents to rebuild.');

        $format = $savedReport->format;

        // Individual reports are one file per document; a legacy row with
        // several documents under "Individual" can only come back as a ZIP.
        if ($format === 'Individual PDFs' && count($documentNumbers) > 1) {
            $format = 'ZIP Archive';
        }

        // A bundle can no longer hold Word-backed documents (see generate()).
        if ($format === 'PDF Bundle' && collect($documentNumbers)->contains(fn ($n) => $this->wordExporter->hasTemplate($n))) {
            $format = 'ZIP Archive';
        }

        $savedBase = pathinfo($savedReport->file_name, PATHINFO_FILENAME) ?: 'Export';
        $file = $this->buildFile($startup, $format, $documentNumbers, $this->sanitizeFileName($savedBase));

        // Keep the name the admin chose, with whatever extension the
        // rebuilt file actually has.
        $file['file_name'] = $this->sanitizeFileName($savedBase).'.'.$file['extension'];

        return $this->fileDownloadResponse($file);
    }

    /**
     * Removes a report from the Reports list. Recipe-only rows have no file;
     * an older row's leftover stored file is deleted along with it.
     */
    public function destroy(SavedReport $savedReport)
    {
        if ($savedReport->file_path !== '') {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($savedReport->file_path);
        }

        $savedReport->delete();

        return back()->with('status', 'Report removed.');
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
    protected function makeBundleFile(string $baseName, array $documentNumbers, Startup $startup): array
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

        return [
            'file_name' => "{$baseName}.pdf",
            'extension' => 'pdf',
            'binary' => $binary,
        ];
    }

    /**
     * One selected document as its own file (.pdf, or .docx for documents
     * rendered from a real Word master).
     */
    protected function makeIndividualFile(int $documentNumber, Startup $startup): array
    {
        $binary = $this->renderDocumentPdf($documentNumber, $startup);
        $extension = $this->extensionFor($documentNumber);
        $label = self::DOCUMENTS[$documentNumber]['label'];

        return [
            'file_name' => $this->sanitizeFileName("{$startup->company_name} - {$label}") . ".{$extension}",
            'extension' => $extension,
            'binary' => $binary,
        ];
    }

    /**
     * @param  array<int, int>  $documentNumbers
     */
    protected function makeZipFile(string $baseName, array $documentNumbers, Startup $startup): array
    {
        // The archive is built on PHP's own local temp path and its bytes are
        // read back and sent to the browser - nothing is kept on the server.
        //
        // (Historical note on why it's not built in public storage:)
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
        // archive on PHP's own local temp path sidesteps that entirely.
        $localPath = sys_get_temp_dir() . '/' . uniqid('export-zip-', true) . '.zip';

        $zip = new ZipArchive();
        $opened = $zip->open($localPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new \RuntimeException("Could not create the ZIP archive (error code {$opened}).");
        }

        foreach ($documentNumbers as $num) {
            $binary = $this->renderDocumentPdf($num, $startup);
            $extension = $this->extensionFor($num);

            $label = self::DOCUMENTS[$num]['label'];
            $individualFileName = $this->sanitizeFileName("{$startup->company_name} - {$label}") . ".{$extension}";

            $zip->addFromString($individualFileName, $binary);
        }

        $zip->close();

        $binary = file_get_contents($localPath);
        @unlink($localPath);

        return [
            'file_name' => "{$baseName}.zip",
            'extension' => 'zip',
            'binary' => $binary,
        ];
    }

    // ============ helpers ============

    /**
     * The generated file as an attachment download. X-Export-File-Name
     * (URL-encoded) lets the export modal read the suggested name without
     * parsing Content-Disposition; no-store keeps browsers/proxies from
     * caching what may be a founder's private documents.
     *
     * @param  array{file_name: string, extension: string, binary: string}  $file
     */
    protected function fileDownloadResponse(array $file): Response
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
        ];

        $asciiFallback = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $file['file_name']) ?: 'export';

        return response($file['binary'], 200, [
            'Content-Type' => $mimeTypes[$file['extension']] ?? 'application/octet-stream',
            'Content-Length' => (string) strlen($file['binary']),
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $file['file_name'], $asciiFallback),
            'X-Export-File-Name' => rawurlencode($file['file_name']),
            'Cache-Control' => 'no-store, private',
        ]);
    }

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
