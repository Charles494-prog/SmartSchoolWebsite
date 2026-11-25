<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Your sign-in code</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<div style="display:none;max-height:0;overflow:hidden;">Your code is {{ $code }}. It expires in 10 minutes.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 12px;">
<tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);">
        <tr><td style="height:6px;background:{{ $accent }};font-size:0;line-height:0;">&nbsp;</td></tr>
        <tr><td align="center" style="padding:32px 32px 8px;">
            @if($logoPath)
                <img src="{{ $message->embed($logoPath) }}" width="64" height="64" alt="{{ $school }}" style="display:block;width:64px;height:64px;border-radius:14px;object-fit:contain;">
            @else
                <table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" valign="middle" style="width:64px;height:64px;border-radius:14px;background:{{ $accent }};color:#ffffff;font-size:26px;font-weight:700;">{{ mb_substr($school, 0, 1) }}</td></tr></table>
            @endif
            <div style="margin-top:12px;font-size:15px;font-weight:700;color:#111827;">{{ $school }}</div>
        </td></tr>
        <tr><td style="padding:16px 32px 0;">
            <h1 style="margin:0;font-size:20px;line-height:1.3;color:#111827;text-align:center;">Your sign-in code</h1>
            <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#4b5563;text-align:center;">Hi {{ $name }}, enter this code to finish signing in to SmartSchool.</p>
        </td></tr>
        <tr><td align="center" style="padding:24px 32px;">
            <div style="display:inline-block;padding:16px 24px;border-radius:12px;background:#eef2ff;border:1px solid #c7d2fe;font-family:'SFMono-Regular',Menlo,Consolas,monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:{{ $accent }};">{{ $code }}</div>
            <p style="margin:14px 0 0;font-size:13px;color:#6b7280;">Expires in 10 minutes · Don't share it with anyone</p>
        </td></tr>
        <tr><td style="padding:0 32px 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;"><tr><td style="padding:14px 16px;font-size:13px;line-height:1.5;color:#4b5563;">
                <b style="color:#111827;">Didn't try to sign in?</b> Someone may know your password. Change it in SmartSchool (My profile) and tell the school office.
            </td></tr></table>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #f0f0f3;font-size:12px;line-height:1.5;color:#9ca3af;text-align:center;">
            {{ $school }}@if($address)<br>{{ $address }}@endif<br>Sent automatically by SmartSchool. Please don't reply.
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
