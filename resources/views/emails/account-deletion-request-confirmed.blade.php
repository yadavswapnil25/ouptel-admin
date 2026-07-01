<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion Request Confirmed</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 480px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="padding: 40px 32px;">
                            <h1 style="margin: 0 0 8px; font-size: 24px; font-weight: 600; color: #18181b;">Account deletion request confirmed</h1>
                            <p style="margin: 0 0 16px; font-size: 15px; color: #71717a; line-height: 1.5;">
                                Hello {{ $userName }},
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #71717a; line-height: 1.5;">
                                Your {{ $appName }} account deletion request has been verified and submitted successfully.
                            </p>
                            <div style="background-color: #f4f4f5; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                                <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #18181b;">What happens next</p>
                                <p style="margin: 0 0 8px; font-size: 14px; color: #52525b; line-height: 1.5;">
                                    • Your account is temporarily deactivated
                                </p>
                                <p style="margin: 0 0 8px; font-size: 14px; color: #52525b; line-height: 1.5;">
                                    • Our admin team will review your request
                                </p>
                                <p style="margin: 0; font-size: 14px; color: #52525b; line-height: 1.5;">
                                    • You will be notified once permanent deletion is processed
                                </p>
                            </div>
                            <p style="margin: 0 0 12px; font-size: 13px; color: #a1a1aa; line-height: 1.5;">
                                If you did not request this, please contact {{ $appName }} support immediately.
                            </p>
                            <p style="margin: 0; font-size: 13px; color: #a1a1aa;">
                                Thank you for being part of {{ $appName }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
