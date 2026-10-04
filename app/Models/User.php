<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /**
     * How long an emailed admin invitation link stays usable.
     */
    public const INVITATION_EXPIRES_HOURS = 48;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'role',
        'account_status',
        'is_first_login',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
        'invitation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_first_login' => 'boolean',
            'is_super_admin' => 'boolean',
            'invitation_sent_at' => 'datetime',
            'module_seen_at' => 'array',
        ];
    }

    /**
     * When this user last opened a module that flags new entries with a red
     * dot (see components/new-dot.blade.php). An entry created after this
     * moment is "new". A module never opened before falls back to when the
     * account itself was created, so a brand-new admin sees what has come in
     * since they joined rather than the module's entire history.
     */
    public function moduleSeenAt(string $module): \Illuminate\Support\Carbon
    {
        $stored = $this->module_seen_at[$module] ?? null;

        return $stored
            ? \Illuminate\Support\Carbon::parse($stored)
            : ($this->created_at ?? now());
    }

    /**
     * Records that the module was just opened. Callers pass the moment the
     * request STARTED (captured before querying what's new) so an entry that
     * lands while the page is still building isn't swallowed as "seen".
     */
    public function markModuleSeen(string $module, ?\Illuminate\Support\Carbon $at = null): void
    {
        $seen = $this->module_seen_at ?? [];
        $seen[$module] = ($at ?? now())->toDateTimeString();

        $this->forceFill(['module_seen_at' => $seen])->save();
    }

    /**
     * Founder "what's new" baseline for one module page: when the founder
     * last opened it. A page never opened since this was added falls back to
     * just before the oldest still-unread notification pointing at it (so
     * whatever that notification announced still shows as new), else now
     * (nothing new).
     */
    public function founderSeenSince(string $module, string $route): \Illuminate\Support\Carbon
    {
        $seen = $this->module_seen_at ?? [];
        if (isset($seen[$module]) && is_string($seen[$module])) {
            return $this->moduleSeenAt($module);
        }

        $oldest = $this->unreadNotifications()->get()
            ->filter(fn ($n) => ($n->data['route'] ?? null) === $route)
            ->min('created_at');

        return $oldest ? \Illuminate\Support\Carbon::parse($oldest)->subSecond() : now();
    }

    /**
     * The founder just opened a module page: stamp it as seen and mark the
     * notifications that point at it read, so the sidebar dot clears
     * (the page itself has already shown this visit's red dots).
     */
    public function markFounderModuleVisited(string $module, string $route, ?\Illuminate\Support\Carbon $at = null): void
    {
        $this->markModuleSeen($module, $at);

        // Only the notifications aimed at the tab/stage currently open.
        \App\Support\PageVisit::markNotificationsSeen(
            $this,
            $route,
            \App\Support\PageVisit::location($route, request()->query()),
        );
    }

    // Relationships
    public function startup()
    {
        return $this->hasOne(Startup::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    // Role helper accessors
    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    /**
     * The one admin who can manage other admin accounts (Manage Admins page).
     * Everything else in the app is identical for every admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->is_super_admin;
    }

    /**
     * An admin who was invited but hasn't opened their link and set a
     * password yet.
     */
    public function isPendingInvitation(): bool
    {
        return $this->isAdmin() && $this->account_status === 'Pending';
    }

    /**
     * An admin whose access was turned off from Manage Admins.
     */
    public function isDisabledAdmin(): bool
    {
        return $this->isAdmin() && $this->account_status === 'Inactive';
    }

    /**
     * Issues a fresh invitation link token (invalidating any earlier link)
     * and returns the plain token for the email. Only its hash is stored.
     */
    public function issueInvitationToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'invitation_token' => hash('sha256', $token),
            'invitation_sent_at' => now(),
        ])->save();

        return $token;
    }

    /**
     * The pending admin a still-valid invitation link belongs to, or null
     * when the token is unknown, already used, or expired.
     */
    public static function findByValidInvitationToken(string $token): ?self
    {
        $user = self::where('invitation_token', hash('sha256', $token))
            ->where('role', 'Admin')
            ->where('account_status', 'Pending')
            ->first();

        if (! $user || ! $user->invitation_sent_at
            || $user->invitation_sent_at->copy()->addHours(self::INVITATION_EXPIRES_HOURS)->isPast()) {
            return null;
        }

        return $user;
    }

    public function isStartup(): bool
    {
        return $this->role === 'Startup';
    }

    /**
     * Account-level approval gate for self-registered Founders — separate
     * from the existing Information Sheet content-approval flow. Accounts
     * created via seeders/admin default to "Active"; self-registered
     * accounts start "Pending" until an admin approves or rejects them.
     */
    public function isApprovedAccount(): bool
    {
        return $this->account_status === 'Active';
    }

    public function isPendingApproval(): bool
    {
        return $this->account_status === 'Pending';
    }

    public function isRejected(): bool
    {
        return $this->account_status === 'Rejected';
    }

    /**
     * Overrides the stock MustVerifyEmail behavior: every time a
     * verification email goes out (registration, or a "resend" click),
     * regenerate the invalidation token first, so any previously sent
     * verification link stops working — only the newest one is valid. See
     * VerifyEmailNotification and VerifyEmailController for the other two
     * pieces of this.
     */
    public function sendEmailVerificationNotification(): void
    {
        // A page load that lands on the verify-email waiting page (fresh
        // after registering, a stale tab reloading, the browser retrying a
        // redirect, a second tab opened on the same link, etc.) used to
        // send a brand new link -- and therefore a brand new email -- every
        // single time this method ran, with nothing stopping two loads a
        // few minutes apart from mailing the founder two identical "Verify
        // Your Email" messages. That's exactly what QA reported (two
        // verification emails ~4 minutes apart with no second "Resend"
        // click in between). A real link only needs to go out once a
        // minute at most -- the "Resend" button already enforces that same
        // 60s cooldown client-side -- so skip re-sending (and don't burn
        // the still-valid link by rotating its token) if one already went
        // out within the last 60 seconds.
        $throttleKey = "email-verification-sent:{$this->id}";

        if (Cache::has($throttleKey)) {
            return;
        }

        Cache::put($throttleKey, true, 60);

        $this->forceFill(['email_verification_token' => Str::random(40)])->save();

        // Don't crash registration if mail is down (e.g. Gmail's daily limit);
        // drop the throttle so the founder can hit "resend" once it's back.
        try {
            $this->notify(new VerifyEmailNotification);
        } catch (\Throwable $e) {
            Cache::forget($throttleKey);
            \Illuminate\Support\Facades\Log::error('Verification email failed', ['user_id' => $this->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The founder's name as First / Middle / Surname. Uses the parts saved
     * from the Startup Profile when there are any; otherwise (accounts saved
     * before those columns existed) falls back to splitting users.name on
     * whitespace, which guesses wrong for two-word first names.
     *
     * @return array{surname: string, first_name: string, middle_name: string}
     */
    public function founderNameParts(): array
    {
        if (filled($this->first_name) || filled($this->last_name)) {
            return [
                'surname' => (string) $this->last_name,
                'first_name' => (string) $this->first_name,
                'middle_name' => (string) $this->middle_name,
            ];
        }

        return InformationSheet::splitFounderName($this->name);
    }
}
