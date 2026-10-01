@props(['url', 'logo' => null])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto; border-collapse: collapse;">
                <tr>
                    <td align="center" style="padding-bottom: 10px;">
                        <table role="presentation" width="56" height="56" cellpadding="0" cellspacing="0" style="width: 56px; height: 56px; background-color: #FBE7EC; border-radius: 14px; border-collapse: collapse;">
                            <tr>
                                <td align="center" valign="middle" style="width: 56px; height: 56px;">
                                    <img src="{{ $logo ?: asset('images/logo/email-logo.png') }}" width="38" height="38" alt="PUP TBIDO" style="display: block; margin: 0 auto; border: 0; width: 38px; height: 38px;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size: 20px; line-height: 1.2; font-weight: 700; color: #6C0E24; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                        PUP TBIDO
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding-top: 3px; font-size: 12px; line-height: 1.4; color: #6B7280; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                        Technology Business Incubator
                    </td>
                </tr>
            </table>
        </a>
    </td>
</tr>