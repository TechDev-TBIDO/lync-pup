<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduledPruningTest extends TestCase
{
    use RefreshDatabase;

    private function notification(User $user, ?\DateTimeInterface $readAt): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'Test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([]),
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_old_read_notifications_are_pruned_but_unread_and_recent_ones_kept(): void
    {
        $user = User::factory()->create();
        $old = $this->notification($user, now()->subDays(91));
        $recent = $this->notification($user, now()->subDays(5));
        $unread = $this->notification($user, null);

        $this->artisan('schedule:test', ['--name' => 'prune-read-notifications'])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $old]);
        $this->assertDatabaseHas('notifications', ['id' => $recent]);
        $this->assertDatabaseHas('notifications', ['id' => $unread]);
    }

    public function test_expired_cache_rows_are_pruned(): void
    {
        DB::table('cache')->insert([
            ['key' => 'expired', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'live', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('schedule:test', ['--name' => 'prune-expired-cache'])->assertSuccessful();

        $this->assertDatabaseMissing('cache', ['key' => 'expired']);
        $this->assertDatabaseHas('cache', ['key' => 'live']);
    }
}
