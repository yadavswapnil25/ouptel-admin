<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} Invitation</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:{{ $accentColor }};color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
                            You have been invited
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;">Hi there,</p>
                            <p style="margin:0 0 14px;line-height:1.6;">
                                <strong>{{ $inviterName }}</strong> invited you to join <strong>{{ $appName }}</strong>.
                                Create your account and connect with colleagues on the platform.
                            </p>
                            <p style="margin:0 0 18px;">
                                <a href="{{ $joinLink }}" style="display:inline-block;background:{{ $accentColor }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">
                                    Join {{ $appName }}
                                </a>
                            </p>
                            <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.5;">
                                If the button does not work, copy this URL into your browser:
                            </p>
                            <p style="margin:0 0 18px;font-size:13px;word-break:break-all;color:#374151;">{{ $joinLink }}</p>
                            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.5;">
                                If you were not expecting this invitation, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
