{{-- The logo is attached to the email itself (header gets it via :logo) so it shows even when the site URL is not reachable. --}}
<x-mail::message :logo="isset($message) && is_file(public_path('images/logo/email-logo.png')) ? $message->embed(public_path('images/logo/email-logo.png')) : null">
# Pitch Deck Requested

Hi {{ $startup->company_name }},

The PUP TBIDO team is requesting your latest pitch deck as part of your incubation progress review.

Please reply to this email and submit your pitch deck at your earliest convenience.

Thanks,<br>
PUP TBIDO Team
</x-mail::message>