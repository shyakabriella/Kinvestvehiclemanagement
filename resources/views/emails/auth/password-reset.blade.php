<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>KINVEST Password Reset</title>
</head>

<body style="margin:0;padding:0;background-color:#f3f5fb;font-family:Arial,Helvetica,sans-serif;color:#17233b;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0"
       width="100%" style="background-color:#f3f5fb;">
    <tr>
        <td align="center" style="padding:40px 16px;">

            <table role="presentation" cellpadding="0" cellspacing="0"
                   border="0" width="100%"
                   style="max-width:560px;background:#ffffff;border:1px solid #e4e9f3;border-radius:18px;">

                <!-- Brand header -->
                <tr>
                    <td style="background:#4f35f5;padding:32px 34px;border-radius:18px 18px 0 0;">
                        <table role="presentation" cellpadding="0"
                               cellspacing="0" border="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <div style="background:#ffffff;color:#4f35f5;width:52px;height:52px;line-height:52px;text-align:center;border-radius:13px;font-weight:800;font-size:27px;">
                                        K
                                    </div>
                                </td>
                                <td style="padding-left:14px;vertical-align:middle;">
                                    <div style="color:#ffffff;font-size:23px;letter-spacing:1px;font-weight:800;">
                                        KINVEST
                                    </div>
                                    <div style="color:#e0dcff;font-size:11px;font-weight:600;letter-spacing:1.5px;padding-top:4px;">
                                        FLEET MANAGEMENT SYSTEM
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Message -->
                <tr>
                    <td style="padding:38px 34px 14px;">

                        <div style="color:#4f35f5;font-size:12px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:12px;">
                            Account Security
                        </div>

                        <h1 style="margin:0 0 20px;font-size:27px;line-height:1.3;color:#14213d;">
                            Reset your password
                        </h1>

                        <p style="margin:0 0 18px;font-size:15px;line-height:1.8;color:#475569;">
                            Hello {{ $recipientName }},
                        </p>

                        <p style="margin:0 0 26px;font-size:15px;line-height:1.8;color:#475569;">
                            We received a request to reset the password for your
                            KINVEST Fleet Management account. Click the button
                            below to securely create a new password.
                        </p>

                        <!-- Primary button -->
                        <table role="presentation" cellpadding="0"
                               cellspacing="0" border="0" width="100%">
                            <tr>
                                <td align="center"
                                    style="padding:8px 0 27px;">
                                    <a href="{{ $resetUrl }}"
                                       style="display:inline-block;background:#4f35f5;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:17px 35px;border-radius:10px;">
                                        Reset My Password &rarr;
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <!-- Expiration notice -->
                        <table role="presentation" width="100%" cellpadding="0"
                               cellspacing="0" border="0"
                               style="background:#f0efff;border-radius:10px;">
                            <tr>
                                <td style="padding:18px 20px;color:#4338ca;font-size:14px;line-height:1.7;">
                                    <strong>Time-sensitive link</strong><br>
                                    This password-reset link expires in
                                    <strong>{{ $expiryMinutes }} minutes</strong>.
                                    For your security, it can only be used once.
                                </td>
                            </tr>
                        </table>

                        <h2 style="margin:30px 0 12px;font-size:16px;color:#17233b;">
                            Didn't request this?
                        </h2>

                        <p style="margin:0 0 22px;font-size:14px;line-height:1.8;color:#64748b;">
                            You can safely ignore this email. Your current
                            password will remain unchanged unless you complete
                            the reset process. Never share your password or
                            password-reset link with anyone.
                        </p>

                        <div style="border-top:1px solid #e6eaf2;padding-top:24px;">
                            <p style="margin:0;color:#475569;font-size:14px;line-height:1.8;">
                                Regards,<br>
                                <strong style="color:#17233b;">KINVEST Support Team</strong><br>
                                Fleet Management System
                            </p>
                        </div>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:25px 34px 32px;">
                        <p style="border-top:1px solid #e6eaf2;padding-top:22px;margin:0;text-align:center;font-size:12px;line-height:1.8;color:#94a3b8;">
                            This is an automated security message from KINVEST.<br>
                            Please do not reply to this email.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="max-width:520px;margin:22px 0 0;text-align:center;font-size:12px;line-height:1.8;color:#94a3b8;">
                &copy; {{ date('Y') }} KINVEST Fleet Management System.
                All rights reserved.
            </p>

        </td>
    </tr>
</table>

</body>
</html>
