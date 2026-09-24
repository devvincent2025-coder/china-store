<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject ?? 'ChinaInNigeria' }}</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=manrope:400,600,700|urbanist:600,700,800&display=swap" rel="stylesheet">
<style>
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #f4f4f5; font-family: 'Manrope', Arial, Helvetica, sans-serif; }
    img { border: 0; line-height: 100%; outline: none; text-decoration: none; }
    .heading { font-family: 'Urbanist', Arial, Helvetica, sans-serif; }
    a.button { display: inline-block; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; font-family: 'Urbanist', Arial, Helvetica, sans-serif; font-size: 14px; }
    @media (max-width: 620px) {
        .email-container { width: 100% !important; }
        .email-padding { padding-left: 20px !important; padding-right: 20px !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:32px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" class="email-container" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#ffffff;padding:24px 32px;border-bottom:3px solid #FF5000;" align="left">
                        <img src="{{ asset('images/branding/icon.png') }}" width="36" height="36" alt="" style="vertical-align:middle;border-radius:8px;">
                        <span class="heading" style="font-size:20px;font-weight:800;vertical-align:middle;margin-left:10px;">
                            <span style="color:#ff5000;">China</span><span style="color:#FF6A00;">In</span><span style="color:#1FA854;">Nigeria</span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="email-padding" style="padding:32px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td class="email-padding" style="padding:20px 32px;background:#fafafa;border-top:1px solid #eee;" align="center">
                        <p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;font-family:'Manrope',Arial,Helvetica,sans-serif;">
                            &copy; {{ date('Y') }} ChinaInNigeria. All rights reserved.<br>
                            <a href="{{ url('/') }}" style="color:#FF5000;text-decoration:none;">Visit our marketplace</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
