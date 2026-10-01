<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Invitation - PUP TBIDO</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding: 32px 32px 24px 32px; text-align:center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto 16px auto;">
                                <tr>
                                    <td style="width:48px; height:48px; background-color:#fdf2f4; border-radius:12px; text-align:center; vertical-align:middle;">
                                        <span style="color:#6D0D23; font-size:22px; font-weight:700;">&#9650;</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0; font-size:20px; font-weight:800; color:#6D0D23; letter-spacing:0.5px;">PUP TBIDO</p>
                            <p style="margin:4px 0 0 0; font-size:13px; color:#6b7280;">Technology Business Incubator</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 32px 32px; text-align:left;">
                            <h1 style="margin:0 0 16px 0; font-size:22px; color:#111827; text-align:center;">You're Invited as an Admin</h1>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                Dear {{ $inviteeName }},
                            </p>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                <strong>{{ $inviterName }}</strong> has invited you to join the LYNC PUP Admin Console
                                of the PUP TBIDO Startup Incubation Program. Click the button below to set your password
                                and activate your account.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto 16px auto;">
                                <tr>
                                    <td style="border-radius:8px; background-color:#6D0D23;">
                                        <a href="{{ $url }}" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">Set Up My Account</a>
                                    </td>
                                </tr>
                            </table>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fdf2f4; border-radius:8px; margin: 0 0 16px 0;">
                                <tr>
                                    <td style="padding: 14px 16px;">
                                        <p style="margin:0; font-size:13px; line-height:20px; color:#4b5563;">
                                            This link expires in <strong>{{ $expiresInHours }} hours</strong> and can only be used once.
                                            If it expires, ask the Super Admin to resend your invitation.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 24px 0; font-size:12px; line-height:18px; color:#9ca3af; word-break:break-all;">
                                If the button doesn't work, copy this link into your browser:<br>{{ $url }}
                            </p>
                            <p style="margin:0; font-size:14px; line-height:22px; color:#4b5563;">
                                Sincerely,<br>
                                <strong>PUP TBIDO Team</strong>
                            </p>
                        </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 24px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                We appreciate the time and effort you invested in the program. Should you have any
                                questions or wish to clarify this decision, feel free to contact us.
                            </p>
                            <p style="margin:0; font-size:14px; line-height:22px; color:#4b5563;">
                                Sincerely,<br>
                                <strong>PUP TBIDO Team</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 32px; background-color:#f9fafb; text-align:center; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">© {{ now()->year }} PUP TBIDO. All rights reserved.</p>
                            <p style="margin:4px 0 0 0; font-size:12px; color:#9ca3af;">Managed by PUP Technology Business Incubator</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
