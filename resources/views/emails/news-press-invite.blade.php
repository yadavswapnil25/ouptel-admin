<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — Press invite</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#2457d3;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
                            You're invited to write for {{ $pressName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;">Hello,</p>
                            <p style="margin:0 0 14px;line-height:1.6;">
                                <strong>{{ $inviterName }}</strong> invited you to join
                                <strong>{{ $pressName }}</strong> as a news editor on OupTel News.
                            </p>
                            <p style="margin:0 0 18px;line-height:1.6;">
                                Apply using the button below. After admin approval, you'll receive login details
                                and be added to this press automatically.
                            </p>
                            <p style="margin:0 0 18px;">
                                <a href="{{ $inviteUrl }}" style="display:inline-block;background:#2457d3;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">
                                    Accept invite &amp; apply
                                </a>
                            </p>
                            @if(!empty($expiresAt))
                                <p style="margin:0 0 10px;font-size:13px;color:#6b7280;line-height:1.5;">
                                    This invite expires on {{ $expiresAt }}.
                                </p>
                            @endif
                            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5;word-break:break-all;">
                                Or open: {{ $inviteUrl }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
