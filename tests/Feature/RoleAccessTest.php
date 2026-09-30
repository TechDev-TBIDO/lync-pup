<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_protected_routes(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        Route::middleware(['auth', 'role:Admin'])
            ->get('/test-admin-route', fn () => 'ok');

        $response = $this->actingAs($admin)
            ->get('/test-admin-route');

        $response->assertOk();
    }

    /**
     * A wrong-role hit on a role-gated route is treated as a stale session
     * (see CheckRole) rather than a hard crash -- this is what actually
     * happens during manual testing when one browser holds an Admin login
     * in one tab and a Founder login in another, since both tabs share the
     * same session cookie. The mismatched session is logged out and sent
     * back to login with an explanation, not left dangling.
     */
    public function test_startup_user_cannot_access_admin_routes(): void
    {
        $startup = User::factory()->create([
            'role' => 'Startup',
        ]);

        Route::middleware(['auth', 'role:Admin'])
            ->get('/test-admin-route', fn () => 'ok');

        $response = $this->actingAs($startup)
            ->get('/test-admin-route');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    /**
     * The JSON/API path is the one case still worth a real 403 -- there's
     * no page to redirect a fetch() call back to.
     */
    public function test_startup_user_gets_a_json_403_when_hitting_admin_routes_via_ajax(): void
    {
        $startup = User::factory()->create([
            'role' => 'Startup',
        ]);

        Route::middleware(['auth', 'role:Admin'])
            ->get('/test-admin-route-json', fn () => 'ok');

        $response = $this->actingAs($startup)
            ->getJson('/test-admin-route-json');

        $response->assertForbidden();
    }

    /**
     * Regression for the "clearing cache fixes it" report: a role-gated
     * page must never be served back out of the browser's Back/Forward
     * cache after a session has moved on, so this asserts every such
     * response is marked uncacheable.
     */
    public function test_role_gated_responses_are_not_browser_cacheable(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        Route::middleware(['auth', 'role:Admin'])
            ->get('/test-admin-cache-route', fn () => 'ok');

        $response = $this->actingAs($admin)
            ->get('/test-admin-cache-route');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function test_startup_can_access_startup_routes(): void
    {
        $startup = User::factory()->create([
            'role' => 'Startup',
        ]);

        Route::middleware(['auth', 'role:Startup'])
            ->get('/test-startup-route', fn () => 'ok');

        $response = $this->actingAs($startup)
            ->get('/test-startup-route');

        $response->assertOk();
    }

    public function test_admin_cannot_access_startup_only_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        Route::middleware(['auth', 'role:Startup'])
            ->get('/test-startup-route', fn () => 'ok');

        $response = $this->actingAs($admin)
            ->get('/test-startup-route');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_is_admin_and_is_startup_helper_methods_work_correctly(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        $startup = User::factory()->create([
            'role' => 'Startup',
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isStartup());

        $this->assertTrue($startup->isStartup());
        $this->assertFalse($startup->isAdmin());
    }
}