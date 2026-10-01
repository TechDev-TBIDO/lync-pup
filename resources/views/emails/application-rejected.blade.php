<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Update - PUP TBIDO</title>
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
                            <h1 style="margin:0 0 16px 0; font-size:22px; color:#111827; text-align:center;">Application Update</h1>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                Dear {{ $startup->user->name }},
                            </p>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                Thank you for your interest in the PUP TBIDO Startup Incubation Program. After careful review, we regret that we are unable to accept your application at this time.
                            </p>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                <strong>Reason:</strong> {{ $startup->rejection_reason }}
                            </p>
                            @if ($startup->admin_remarks)
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                <strong>Additional Remarks:</strong> {{ $startup->admin_remarks }}
                            </p>
                            @endif
                            <p style="margin:0 0 24px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                We appreciate your efforts and encourage you to continue developing your startup. We wish you the best in your future endeavors.
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
