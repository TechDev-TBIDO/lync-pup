<?php

namespace Tests\Feature;

use App\Mail\CoordinatorAssignedMail;
use App\Models\Coordinator;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Portal emails go out from a no-reply sender; replies must reach the
 * office inbox (ico@pup.edu.ph), and the legal pages list that address.
 */
class MailReplyToTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_emails_reply_to_the_ico_inbox(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'no-reply@puptbi.site']);
        Mail::purge('array');

        $sent = null;
        Event::listen(MessageSent::class, function (MessageSent $event) use (&$sent) {
            $sent = $event->message;
        });

        $user = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $user->id]);
        Mail::to('coord@example.com')->send(CoordinatorAssignedMail::make($startup, Coordinator::factory()->create()));

        $this->assertNotNull($sent);
        $this->assertSame('ico@pup.edu.ph', $sent->getReplyTo()[0]->getAddress());
    }

    public function test_the_legal_pages_list_the_ico_inbox(): void
    {
        $this->get(route('legal.terms'))->assertOk()->assertSee('ico@pup.edu.ph')->assertDontSee('tbido@pup.edu.ph');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('ico@pup.edu.ph')->assertDontSee('tbido@pup.edu.ph');
    }
}
