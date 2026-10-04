<?php

namespace App\Support;

/**
 * Shared "how will this meeting happen" concept — the same fixed platform
 * list, link placeholders, and per-platform link validation originally
 * built for the Roadblock mentor-assignment modal (see
 * App\Http\Requests\Admin\AssignRoadblockRequest::platformLinkValidator()),
 * factored out here so the evaluation-schedule "Modality" field can reuse
 * the exact same options/validation instead of re-deriving them.
 */
class MeetingPlatform
{
    public const OPTIONS = ['Google Meet', 'Zoom', 'Microsoft Teams', 'Location', 'Custom Link'];

    public const LINK_PLACEHOLDERS = [
        'Google Meet' => 'e.g., https://google.com',
        'Zoom' => 'e.g., https://zoom.us',
        'Microsoft Teams' => 'e.g., Paste Microsoft Teams invitation link here',
        'Location' => 'e.g., 123 Main Street, Suite 400, New York, NY',
        'Custom Link' => 'e.g., https://your-conferencing-app.com',
    ];

    /**
     * Per-platform validation for the link/address field, matching each
     * platform's expected shape:
     *   - Google Meet: must be a meet.google.com link.
     *   - Zoom: must be a zoom.us "/j/" or "/my/" meeting link.
     *   - Microsoft Teams: loose check for a microsoft.com or live.com link
     *     (Teams links are served from either domain depending on account
     *     type).
     *   - Location: just a minimum-length sanity check — actually verifying
     *     a string "looks like" a real address is unreliable, so this only
     *     guards against obviously-too-short input.
     *   - Custom Link: generic check that it's at least a well-formed
     *     http(s) URL, since it could point anywhere.
     */
    public static function isValidLink(?string $platform, string $value): bool
    {
        $value = trim($value);

        // Links must be the URL alone (no spaces) - normalizeLink() already pulled
        // the URL out of a pasted invite, so anything left with spaces is not a link.
        return match ($platform) {
            'Google Meet' => (bool) preg_match('~^https?://meet\.google\.com/\S+$~i', $value),
            'Zoom' => (bool) preg_match('~^https?://([a-z0-9-]+\.)*zoom\.us/(j|my)/\S+$~i', $value),
            'Microsoft Teams' => (bool) preg_match('~^https?://([a-z0-9-]+\.)*(microsoft\.com|live\.com)(/\S*)?$~i', $value),
            'Location' => mb_strlen($value) >= 8,
            'Custom Link' => (bool) preg_match('~^https?://\S+$~i', $value),
            default => true,
        };
    }

    /**
     * People often paste a whole calendar invite instead of just the link, e.g.
     *   "(No title) Tuesday, October 6 · 12:30 – 1:30pm Time zone: Asia/Manila
     *    Google Meet joining info Video call link: https://meet.google.com/abc-defg-hij"
     * For every platform except Location this pulls the meeting URL out of the
     * text (preferring one on the platform's own domain), so only the link is
     * saved and the Join button works. Text with no URL is returned trimmed and
     * left for isValidLink() to reject.
     */
    public static function normalizeLink(?string $platform, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($platform === 'Location' || $value === '') {
            return $value;
        }

        if (! preg_match_all('~https?://[^\s<>"\']+~i', $value, $matches)) {
            return $value;
        }

        // Drop punctuation that sticks to a URL at the end of a sentence.
        $urls = array_map(fn ($u) => rtrim($u, '.,;:!?)]}'), $matches[0]);

        $preferred = match ($platform) {
            'Google Meet' => '~^https?://meet\.google\.com/~i',
            'Zoom' => '~^https?://([a-z0-9-]+\.)*zoom\.us/(j|my)/~i',
            'Microsoft Teams' => '~^https?://([a-z0-9-]+\.)*(microsoft\.com|live\.com)~i',
            default => null,
        };

        if ($preferred) {
            foreach ($urls as $url) {
                if (preg_match($preferred, $url)) {
                    return $url;
                }
            }
        }

        return $urls[0];
    }

    public static function linkErrorMessage(?string $platform): string
    {
        return match ($platform) {
            'Google Meet' => 'Please enter a valid Google Meet link (e.g. https://meet.google.com/xxx-xxxx-xxx).',
            'Zoom' => 'Please enter a valid Zoom link (must include zoom.us/j/ or zoom.us/my/).',
            'Microsoft Teams' => 'Please enter a valid Microsoft Teams link.',
            'Location' => 'Please enter a more complete address.',
            'Custom Link' => 'Please enter a valid link starting with http:// or https://.',
            default => 'Please enter a valid meeting link or location.',
        };
    }
}
