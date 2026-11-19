<?php

namespace App\Http\Controllers;

use App\Models\InformationSheet;
use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

class StorageController extends Controller
{
    /**
     * Folders whose files are meant to be seen by anyone: profile photos,
     * including the startup photos shown on the public landing page.
     */
    private const PUBLIC_FOLDERS = ['startups', 'mentors', 'coordinators'];

    /** Photos never change at a given path (random file names), so cache them hard. */
    private const PUBLIC_CACHE = 'public, max-age=604800, immutable';

    /**
     * Founders' documents: cached only in the viewer's own browser, and only
     * briefly, so a revoked account stops seeing them soon after.
     */
    private const PRIVATE_CACHE = 'private, max-age=3600';

    /**
     * Serves a file from the "public" disk (Supabase Storage in production,
     * storage/app/public locally) at its "/storage/{path}" URL.
     *
     * Despite the disk's name, only profile photos are public. Founders'
     * supporting documents (information-sheets/) and roadblock attachments
     * (roadblocks/) need a logged-in user who is either an active admin or
     * the founder who owns them. Anything else on the disk is admin-only.
     */
    public function show(string $path, Request $request): Response
    {
        // Flysystem already rejects ".." path traversal internally, but
        // bail out explicitly first so a malformed path never even
        // reaches the disk.
        if (str_contains($path, '..')) {
            abort(404);
        }

        $isPublic = $this->isPublicPath($path);

        if (! $isPublic) {
            $user = $request->user();

            if (! $user) {
                // A founder whose session expired lands back on the file after logging in.
                return redirect()->guest(route('login'));
            }

            // 404 rather than 403, so a logged-in user can't probe which paths exist.
            abort_unless($this->canView($user, $path), 404);
        }

        // One read, MIME type from the extension: every extra Storage call
        // is a network round trip to Supabase.
        $stream = Storage::disk('public')->readStream($path);

        if (! is_resource($stream)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';

        $filename = $request->query('name') ?: basename($path);
        $fallback = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $filename) ?: 'file';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, $fallback),
            'Cache-Control' => $isPublic ? self::PUBLIC_CACHE : self::PRIVATE_CACHE,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isPublicPath(string $path): bool
    {
        return in_array(strtok($path, '/'), self::PUBLIC_FOLDERS, true);
    }

    private function canView(User $user, string $path): bool
    {
        if ($user->isAdmin()) {
            return ! $user->isDisabledAdmin() && ! $user->isPendingInvitation();
        }

        if (! $user->isStartup() || ! $user->startup) {
            return false;
        }

        $segments = explode('/', $path);
        $ownerId = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;

        if ($ownerId === null) {
            return false;
        }

        $startupId = $user->startup->startup_id;

        return match ($segments[0]) {
            'information-sheets' => InformationSheet::whereKey($ownerId)->where('startup_id', $startupId)->exists(),
            'roadblocks' => Roadblock::whereKey($ownerId)->where('startup_id', $startupId)->exists(),
            default => false,
        };
    }
}
