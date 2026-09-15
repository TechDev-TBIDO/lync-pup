<?php

namespace App\Http\Controllers\Startup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Startup\StoreIncubationInvolvementRequest;
use App\Http\Requests\Startup\StoreInformationSheetFilesRequest;
use App\Http\Requests\Startup\StoreLdInterventionRequest;
use App\Http\Requests\Startup\StoreStartupReferenceRequest;
use App\Http\Requests\Startup\UpdateIncubationInvolvementRequest;
use App\Http\Requests\Startup\UpdateInformationSheetRequest;
use App\Http\Requests\Startup\UpdateLdInterventionRequest;
use App\Http\Requests\Startup\UpdateStartupReferenceRequest;
use App\Models\IncubationInvolvement;
use App\Models\InformationSheetFile;
use App\Models\LdIntervention;
use App\Models\StartupReference;
use App\Traits\CompressesImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InformationSheetController extends Controller
{
    use CompressesImages;

    public function edit(): View|RedirectResponse
    {
        $startup = auth()->user()->startup->load([
            'informationSheet.incubationInvolvements',
            'informationSheet.ldInterventions',
            'informationSheet.references',
            'informationSheet.files',
            'teamMembers',
        ]);

        // The Information Sheet is a step that comes after the Startup
        // Profile in the onboarding tracker — sent back to finish the
        // Profile first rather than shown a form they can't meaningfully
        // submit yet (their name/photo/contact details would still be
        // missing from every generated export).
        if (! $startup->isProfileComplete()) {
            return redirect()
                ->route('startup.profile.edit')
                ->with('status', 'Please complete your Startup Profile first before filling out the Information Sheet.');
        }

        return view('startup.information-sheet.edit', [
            'startup' => $startup,
            'prefill' => $this->prefillFromProfile($startup),
        ]);
    }

    public function update(UpdateInformationSheetRequest $request): RedirectResponse|Response
    {
        // Profile-completeness, approval-lock and evaluation-day-lock are
        // all checked in UpdateInformationSheetRequest::authorize() now, so
        // they run before field validation and always produce a clean 403
        // rather than getting masked by a "fix these fields" redirect.
        $startup = auth()->user()->startup;
        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);

        // The Information Sheet page submits every section (this main form,
        // every Core Team row, every Incubation/L&D/Reference row) as its own
        // independent request — see submitInfoSheetForms() in the edit view.
        // To make the overall Save genuinely all-or-nothing, the page first
        // fires every request in "dry run" mode (validation only, nothing
        // persisted) and only re-fires for real once every section comes
        // back clean. _dry_run short-circuits here, after the request's own
        // authorization and validation above have already run, so a locked
        // sheet or an invalid field still fails the dry run exactly like a
        // real save would.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $data = $request->validated();

        // A draft Save just persists whatever's filled in — it never touches
        // approval_status/submission_date, so it can't accidentally re-open
        // an Approved/Rejected sheet or restart the evaluation countdown.
        // Only an explicit Submit does that (see below).
        if ($request->isDraftSave()) {
            $sheet->update($data);

            return redirect()->route('startup.information-sheet.edit')->with('status', 'Information Sheet saved. Submit it for review once it\'s complete.');
        }

        $data['approval_status'] = 'Pending';
        $data['submission_date'] = now();

        // "Date accomplished" is the day the founder filled the form in, so it
        // is stamped here rather than typed — every submit re-dates the sheet,
        // matching submission_date above.
        $data['date_accomplished'] = now();

        $sheet->update($data);

        // One-way sync into the Startup Profile (Info Sheet -> Profile only,
        // never the reverse — the Profile's own edit page never reads from
        // the sheet). Every real save here overwrites the Profile's copy of
        // these 3 fields, not just a one-time seed like the blank-only
        // business_description backfill in StartupProfileController::update().
        // Same locked window as everything else that writes into these
        // shared records (see Startup::isInformationSheetLocked()'s
        // docblock): once the sheet is Approved or today is the scheduled
        // evaluation day, the Profile stops being touched by it entirely.
        // Address shapes: the Profile has one general "Address" field, so it
        // takes the sheet's Residential Address rather than the Permanent one.
        if (! $startup->isInformationSheetLocked()) {
            $startup->update([
                'business_description' => $data['business_description'] ?? $startup->business_description,
                'contact_phone' => $data['mobile_no'] ?? $startup->contact_phone,
                'location' => $data['residential_address'] ?? $startup->location,
            ]);
        }

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Information Sheet submitted for review.');
    }

    /**
     * Values used to pre-fill sheet fields that are still empty, taken from
     * the Startup Profile the founder already completed. Nothing is written to
     * the database here — these only seed the inputs, so the founder reviews
     * and corrects them before the first save.
     *
     * One-way on purpose: the sheet's copy is its own record (the founder can
     * edit it freely here) and saving it never writes back to the Startup
     * Profile or the user account. Fields whose shapes don't match (profile
     * "location" is a city, the sheet wants a street address) are left out.
     */
    private function prefillFromProfile($startup): array
    {
        $name = \App\Models\InformationSheet::splitFounderName($startup->user?->name);

        return [
            'surname' => $name['surname'],
            'first_name' => $name['first_name'],
            'middle_name' => $name['middle_name'],
            'mobile_no' => (string) $startup->contact_phone,
            'founder_email' => (string) $startup->user?->email,
        ];
    }

    // Incubation Involvement
    public function storeIncubation(StoreIncubationInvolvementRequest $request): RedirectResponse|Response
    {
        // See the note on _dry_run in update() above — same all-or-nothing scheme.
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $sheet = auth()->user()->startup->informationSheet;
        $sheet->incubationInvolvements()->create($request->validated());

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Incubation involvement added.');
    }

    public function updateIncubation(UpdateIncubationInvolvementRequest $request, IncubationInvolvement $incubationInvolvement): RedirectResponse|Response
    {
        abort_unless($incubationInvolvement->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);

        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $incubationInvolvement->update($request->validated());

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Updated.');
    }

    public function destroyIncubation(IncubationInvolvement $incubationInvolvement): RedirectResponse
    {
        abort_unless($incubationInvolvement->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);
        $incubationInvolvement->delete();

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Removed.');
    }

    // L&D Interventions
    public function storeLd(StoreLdInterventionRequest $request): RedirectResponse|Response
    {
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $sheet = auth()->user()->startup->informationSheet;
        $sheet->ldInterventions()->create($request->validated());

        return redirect()->route('startup.information-sheet.edit')->with('status', 'L&D intervention added.');
    }

    public function updateLd(UpdateLdInterventionRequest $request, LdIntervention $ldIntervention): RedirectResponse|Response
    {
        abort_unless($ldIntervention->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);

        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $ldIntervention->update($request->validated());

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Updated.');
    }

    public function destroyLd(LdIntervention $ldIntervention): RedirectResponse
    {
        abort_unless($ldIntervention->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);
        $ldIntervention->delete();

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Removed.');
    }

    // References
    public function storeReference(StoreStartupReferenceRequest $request): RedirectResponse|Response
    {
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $sheet = auth()->user()->startup->informationSheet;
        $sheet->references()->create($request->validated());

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Reference added.');
    }

    public function updateReference(UpdateStartupReferenceRequest $request, StartupReference $reference): RedirectResponse|Response
    {
        abort_unless($reference->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);

        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $reference->update($request->validated());

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Updated.');
    }

    public function destroyReference(StartupReference $reference): RedirectResponse
    {
        abort_unless($reference->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);
        $reference->delete();

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Removed.');
    }

    // Supporting Documents — optional, multi-file attachments on the sheet
    // itself (see InformationSheetFile). Same store-under-its-own-folder,
    // compress-if-image approach as Roadblock submission's own Supporting
    // Files (see RoadblockController::store()).
    public function storeFile(StoreInformationSheetFilesRequest $request): RedirectResponse|Response
    {
        if ($request->boolean('_dry_run')) {
            return response()->noContent();
        }

        $startup = auth()->user()->startup;
        $sheet = $startup->informationSheet()->firstOrCreate(['startup_id' => $startup->startup_id]);

        foreach ($request->file('files', []) as $file) {
            $isImage = str_starts_with($file->getMimeType(), 'image/');

            $path = $isImage
                ? $this->compressAndStoreImage($file, "information-sheets/{$sheet->info_sheet_id}")
                : $file->store("information-sheets/{$sheet->info_sheet_id}", 'public');

            $sheet->files()->create([
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'is_image' => $isImage,
            ]);
        }

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Supporting documents added.');
    }

    public function destroyFile(InformationSheetFile $file): RedirectResponse
    {
        abort_unless($file->informationSheet->startup_id === auth()->user()->startup->startup_id, 403);

        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        return redirect()->route('startup.information-sheet.edit')->with('status', 'Supporting document removed.');
    }
}