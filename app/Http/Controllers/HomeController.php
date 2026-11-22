<?php

namespace App\Http\Controllers;

use App\Models\AssessmentDocument;
use App\Models\Cohort;
use App\Models\Startup;
use App\Support\VentureExitForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The app's front door at "/": sign in and founder sign-up on one page, next
 * to the program's headline numbers. Replaced the old marketing landing page
 * and the separate /login and /register pages (both now redirect here).
 */
class HomeController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Already signed in: go where a fresh sign-in would have sent them.
        if ($user = $request->user()) {
            if ($user->role === 'Startup' && ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return redirect()->route($user->role === 'Startup' ? 'startup.dashboard' : 'dashboard');
        }

        $mode = old('form') === 'register' || $request->query('mode') === 'signup' ? 'signup' : 'signin';

        return view('home', [
            'mode' => $mode,
            'stats' => Cache::remember('home-stats:v1', 300, fn () => $this->stats()),
            // The cohort whose call for applications is open (null = closed).
            'openCohort' => Cohort::openCall()?->display_label,
        ]);
    }

    /**
     * Headline numbers for accepted startups (Approved Information Sheet).
     * Same rules the old landing page used.
     *
     * @return array{active_ventures: int, sectors: int, graduated: int}
     */
    protected function stats(): array
    {
        $startups = Startup::query()
            ->whereHas('informationSheet', fn ($q) => $q->where('approval_status', 'Approved'))
            ->get(['startup_id', 'industry_sector']);

        $exitStatuses = AssessmentDocument::whereIn('startup_id', $startups->pluck('startup_id'))
            ->where('stage', 'Venture Exit')
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->get()
            ->mapWithKeys(fn ($doc) => [$doc->startup_id => data_get($doc->data, 'exit_status')])
            ->filter(fn ($status) => in_array($status, ['Completed', 'Graduated'], true));

        return [
            // Startups that have exited (Completed or Graduated) are no longer active.
            'active_ventures' => $startups->count() - $exitStatuses->count(),
            // Sector is free text, so spelling variants ("AgriTech", "agri-tech ")
            // count once: compared ignoring case, spaces and punctuation.
            'sectors' => $startups
                ->pluck('industry_sector')
                ->map(fn ($sector) => preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower(trim((string) $sector))))
                ->filter()
                ->unique()
                ->count(),
            'graduated' => $exitStatuses->filter(fn ($status) => $status === 'Graduated')->count(),
        ];
    }
}
