<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dev OTP Log</title>
    <meta http-equiv="refresh" content="5">
    <style>
        body { font-family: -apple-system, Helvetica, Arial, sans-serif; background: #f4f4f5; margin: 0; padding: 32px; }
        h1 { font-size: 18px; color: #111827; }
        p.hint { color: #6b7280; font-size: 13px; margin-top: -8px; }
        table { width: 100%; max-width: 720px; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        th, td { text-align: left; padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
        th { background: #fafafa; color: #6b7280; text-transform: uppercase; font-size: 11px; letter-spacing: 0.03em; }
        td.code { font-family: ui-monospace, Menlo, monospace; font-weight: 700; font-size: 15px; color: #ea580c; letter-spacing: 0.05em; }
        td.identifier { color: #374151; }
        td.time { color: #9ca3af; white-space: nowrap; }
        .empty { color: #9ca3af; font-size: 13px; padding: 20px 0; }
    </style>
</head>
<body>
    <h1>Dev OTP Log</h1>
    <p class="hint">Local-only — never runs in staging/production (see App\Support\Dev\OtpLogger). Auto-refreshes every 5s.</p>

    @if ($logs->isEmpty())
        <p class="empty">No OTPs generated yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Purpose</th>
                    <th>Sent to</th>
                    <th>Code</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->purpose }}</td>
                        <td class="identifier">{{ $log->identifier }}</td>
                        <td class="code">{{ $log->code }}</td>
                        <td class="time">{{ $log->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
