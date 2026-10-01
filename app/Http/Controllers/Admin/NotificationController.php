<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class NotificationController extends Controller
{
    /**
     * Mirrors Startup\NotificationController::show() exactly: opening one of
     * the Admin Dashboard's update cards marks that notification read and
     * forwards to wherever it points, so an admin who reaches the same page
     * some other way still has the card waiting for them.
     */
    public function show(string $notification): RedirectResponse
    {
        $record = auth()->user()->notifications()->whereKey($notification)->firstOrFail();

        $record->markAsRead();

        $route = $record->data['route'] ?? null;

        if (! $route || ! Route::has($route)) {
            return redirect()->route('dashboard');
        }

        // Land on the exact tab and pulse the item the card is about
        // (?tab=, ?highlight= - see App\Support\PageVisit and app.js).
        $params = [
            ...\App\Support\PageVisit::target($record),
            ...(array) ($record->data['route_params'] ?? []),
        ];

        return redirect()->route($route, $params);
    }
}
