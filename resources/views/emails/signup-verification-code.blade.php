<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 480px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="padding: 40px 32px;">
                            <h1 style="margin: 0 0 8px; font-size: 24px; font-weight: 600; color: #18181b;">Verify your email</h1>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #71717a; line-height: 1.5;">Use the code below to complete your {{ $appName }} signup.</p>
                            <div style="background-color: #f4f4f5; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 24px;">
                                <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #18181b;">{{ $code }}</span>
                            </div>
                            <p style="margin: 0; font-size: 13px; color: #a1a1aa;">This code expires in 15 minutes. Do not share it with anyone.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
