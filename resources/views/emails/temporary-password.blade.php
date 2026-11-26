<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UCUREC Ethics Review</title>
</head>
<body style="margin:0; padding:0; background-color:#fdf9ec; font-family:Arial, Helvetica, sans-serif; color:#1f1f1f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fdf9ec; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #eee;">
                <tr>
                    <td style="background-color:#c11f33; border-bottom:4px solid #fdca0a; padding:20px 24px; color:#ffffff;">
                        <div style="font-size:20px; font-weight:bold;">UCUREC Ethics Review</div>
                        <div style="font-size:13px; color:#ffe27a;">Urdaneta City University Research Ethics Committee</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px; font-size:15px; line-height:1.5;">
                        <p style="margin:0 0 12px;">Hello {{ $user->name }},</p>

                        @if ($isReset)
                            <p style="margin:0 0 12px;">Your password for the UCUREC Ethics Review system was reset by the IT administrator.</p>
                        @else
                            <p style="margin:0 0 12px;">An account was created for you in the UCUREC Ethics Review system as <strong>{{ $user->role->label() }}</strong>.</p>
                        @endif

                        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; margin:16px 0; background-color:#fdf9ec; border:1px solid #f2e3a6; border-radius:6px;">
                            <tr>
                                <td style="padding:14px 16px; font-size:14px;">
                                    <div style="margin-bottom:6px;"><span style="color:#666;">Email:</span> <strong>{{ $user->email }}</strong></div>
                                    <div><span style="color:#666;">Temporary password:</span>
                                        <strong style="font-family:Consolas, 'Courier New', monospace; font-size:16px; letter-spacing:1px;">{{ $password }}</strong>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px;">
                            <a href="{{ $signInUrl }}" style="display:inline-block; background-color:#c11f33; color:#ffffff; text-decoration:none; padding:10px 18px; border-radius:6px; font-weight:bold;">Sign in</a>
                        </p>

                        <p style="margin:0 0 8px; font-size:13px; color:#444;">
                            You will be asked to choose a new password the first time you sign in.
                            Do not share this password with anyone, and delete this email after you have changed it.
                        </p>
                        <p style="margin:0; font-size:13px; color:#444;">
                            If you did not expect this email, contact the REC Secretariat or the IT administrator.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 24px; background-color:#fafafa; font-size:12px; color:#888;">
                        This is an automated message from the UCUREC Ethics Review system. Please do not reply.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
