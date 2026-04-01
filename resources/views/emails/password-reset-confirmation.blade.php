<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} Password Changed</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#16a34a;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
                            {{ $appName }} Password Updated
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;">Hello {{ $user->first_name ?: $user->username }},</p>
                            <p style="margin:0 0 12px;line-height:1.6;">
                                Your password was changed successfully.
                            </p>
                            <p style="margin:0;line-height:1.6;color:#b91c1c;">
                                If you did not perform this action, please contact support immediately.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
