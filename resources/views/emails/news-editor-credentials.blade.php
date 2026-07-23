<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — Editor Access</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#2457d3;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
                            You're approved as a news editor
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;">Hi {{ $name }},</p>
                            <p style="margin:0 0 14px;line-height:1.6;">
                                Your application to write on <strong>OupTel News</strong> was approved.
                                Use the details below to sign in and open your Editor Panel.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:0 0 18px;">
                                <tr>
                                    <td style="padding:14px 16px;font-size:14px;line-height:1.7;">
                                        <div><strong>Username:</strong> {{ $username }}</div>
                                        <div><strong>Email:</strong> {{ $email }}</div>
                                        @if(!empty($password))
                                            <div><strong>Temporary password:</strong> {{ $password }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if(!empty($password))
                                <p style="margin:0 0 14px;font-size:13px;color:#6b7280;line-height:1.5;">
                                    Please change this password after your first login.
                                </p>
                            @else
                                <p style="margin:0 0 14px;font-size:13px;color:#6b7280;line-height:1.5;">
                                    Your existing Ouptel account was granted editor access. Sign in with your current password.
                                </p>
                            @endif

                            <p style="margin:0 0 18px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#2457d3;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">
                                    Log in to Ouptel
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.5;">
                                After login, open News → Editor Panel to set up your press page and submit articles.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
