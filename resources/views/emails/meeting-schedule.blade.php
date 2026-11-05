<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $heading }} - PUP TBIDO</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding: 32px 32px 24px 32px; text-align:center;">
                            <p style="margin:0; font-size:20px; font-weight:800; color:#6D0D23; letter-spacing:0.5px;">PUP TBIDO</p>
                            <p style="margin:4px 0 0 0; font-size:13px; color:#6b7280;">Technology Business Incubator</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 32px 32px; text-align:left;">
                            <h1 style="margin:0 0 16px 0; font-size:22px; color:#111827; text-align:center;">{{ $heading }}</h1>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                Dear {{ $recipientName }},
                            </p>
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563;">
                                {{ $intro }}
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fdf2f4; border-radius:8px; margin: 0 0 16px 0;">
                                @foreach ($details as $row)
                                <tr>
                                    <td valign="top" style="padding: {{ $loop->first ? '14px' : '4px' }} 8px {{ $loop->last ? '14px' : '4px' }} 16px; width:100px; font-size:13px; line-height:20px; font-weight:700; color:#6D0D23;">{{ $row['label'] }}</td>
                                    <td valign="top" style="padding: {{ $loop->first ? '14px' : '4px' }} 16px {{ $loop->last ? '14px' : '4px' }} 0; font-size:13px; line-height:20px; color:#4b5563; word-break:break-word;">
                                        @if (! empty($row['href']))
                                            <a href="{{ $row['href'] }}" style="color:#6D0D23;">{{ $row['value'] }}</a>
                                        @else
                                            {{ $row['value'] }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                            @if (filled($notes))
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#4b5563; word-break:break-word;">
                                <strong>Note:</strong> {{ $notes }}
                            </p>
                            @endif
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto 24px auto;">
                                <tr>
                                    <td style="border-radius:8px; background-color:#6D0D23;">
                                        <a href="{{ $meetingsUrl }}" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">View Meeting</a>
                                    </td>
                                </tr>
                            </table>
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
