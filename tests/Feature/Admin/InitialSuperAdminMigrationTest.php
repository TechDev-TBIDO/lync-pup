<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialSuperAdminMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/0001_01_01_000076_assign_initial_super_admin.php');
        $migration->up();
    }

    public function test_promotes_the_configured_admin_when_no_super_admin_exists(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'email' => 'head@pup.edu.ph']);
        config(['app.super_admin_email' => 'Head@PUP.edu.ph']);

        $this->runMigration();

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_does_nothing_when_a_super_admin_already_exists(): void
    {
        $current = User::factory()->create(['role' => 'Admin']);
        $current->forceFill(['is_super_admin' => true])->save();
        $other = User::factory()->create(['role' => 'Admin', 'email' => 'head@pup.edu.ph']);
        config(['app.super_admin_email' => 'head@pup.edu.ph']);

        $this->runMigration();

        $this->assertTrue($current->fresh()->isSuperAdmin());
        $this->assertFalse($other->fresh()->isSuperAdmin());
    }

    public function test_never_promotes_a_founder_account(): void
    {
        $founder = User::factory()->create(['role' => 'Startup', 'email' => 'head@pup.edu.ph']);
        config(['app.super_admin_email' => 'head@pup.edu.ph']);

        $this->runMigration();

        $this->assertFalse((bool) $founder->fresh()->is_super_admin);
    }
}
