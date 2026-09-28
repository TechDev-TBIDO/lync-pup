<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_first_login' => 'boolean',
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

        $this->unreadNotifications()->get()
            ->filter(fn ($n) => ($n->data['route'] ?? null) === $route)
            ->each->markAsRead();
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
        $this->forceFill(['email_verification_token' => Str::random(40)])->save();

        $this->notify(new VerifyEmailNotification);
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
