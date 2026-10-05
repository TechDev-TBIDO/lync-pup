<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every "Apply Now" on the landing page opens the sign-up page with
 * ?from=landing, so that page's Back button returns to the landing page
 * (also after a failed submit) instead of going to Login.
 */
class LandingApplyNowBackTest extends TestCase
{
    use RefreshDatabase;

    private function backLink(string $html): ?string
    {
        return preg_match('#<a href="([^"]+)" class="inline-flex text-gray-500 hover:text-gray-800 mb-6">#', $html, $m) ? html_entity_decode($m[1]) : null;
    }

    public function test_every_apply_now_returns_to_the_landing_page(): void
    {
        preg_match_all('#<a[^>]*href="([^"]*/register[^"]*)"[^>]*>\s*Apply Now#', $this->get('/')->assertOk()->getContent(), $links);
        $this->assertNotEmpty($links[1], 'landing page has Apply Now');

        foreach (array_map('html_entity_decode', $links[1]) as $url) {
            $this->assertStringContainsString('from=landing', $url, "Apply Now link {$url}");
            $this->assertSame(route('welcome'), $this->backLink($this->get($url)->assertOk()->getContent()), 'Back goes to the landing page');

            // A failed submit returns to the same URL - Back still goes to the landing page.
            $redirect = $this->from($url)->post(route('register'), [])->headers->get('Location');
            $this->assertSame(route('welcome'), $this->backLink($this->get($redirect)->getContent()));
        }

        // Opened from Login's "Create one now": Back still goes to Login.
        $this->assertSame(route('login'), $this->backLink($this->get(route('register'))->getContent()));
    }
}
