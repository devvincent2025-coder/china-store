<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Report' }} — ChinaInNigeria</title>
    <style>
        @page {
            margin: 30px 36px 55px 36px;
            @bottom-center {
                content: "Page " counter(page) " of " counter(pages);
                font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
                font-size: 9px;
                color: #999999;
            }
        }

        * { box-sizing: border-box; }

        /* DejaVu Sans (bundled with dompdf) first, not last — Helvetica/Arial have no glyph for
           the Naira sign (₦, U+20A6), so any amount formatted via App\Support\Money::naira() was
           silently rendering as "?" in every PDF export using this layout. DejaVu Sans covers it
           correctly and still reads like a clean, professional sans-serif. */
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            color: #333333;
            font-size: 11px;
            line-height: 1.4;
        }

        .report-header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #FF5000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .report-header .logo-cell {
            display: table-cell;
            vertical-align: middle;
            width: 50px;
        }

        .report-header .brand-cell {
            display: table-cell;
            vertical-align: middle;
            padding-left: 10px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
        }

        .brand-name .china { color: #ff5000; }
        .brand-name .in { color: #FF6A00; }
        .brand-name .nigeria { color: #1FA854; }

        .brand-tagline {
            font-size: 9px;
            color: #999999;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .report-meta {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            font-size: 9px;
            color: #777777;
        }

        .report-meta .meta-left { display: table-cell; text-align: left; }
        .report-meta .meta-right { display: table-cell; text-align: right; }

        .report-title {
            font-size: 17px;
            font-weight: bold;
            color: #18181b;
            margin: 14px 0 2px;
        }

        .report-subtitle {
            font-size: 10px;
            color: #777777;
            margin-bottom: 16px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        table.data-table th {
            background: #FF5000;
            color: #ffffff;
            text-align: left;
            padding: 6px 7px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table.data-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #eeeeee;
            font-size: 9.5px;
        }

        table.data-table tr:nth-child(even) td {
            background: #fafafa;
        }

        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            table-layout: fixed;
        }

        .summary-card {
            display: table-cell;
            padding: 10px;
            border: 1px solid #eeeeee;
        }

        .summary-label {
            font-size: 8px;
            color: #999999;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-value {
            font-size: 15px;
            font-weight: bold;
            color: #18181b;
            margin-top: 2px;
        }

        .section-heading {
            font-size: 12px;
            font-weight: bold;
            color: #18181b;
            margin: 20px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #eeeeee;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
        }

        .badge-green { background: #dcfce7; color: #15803d; }
        .badge-amber { background: #fef3c7; color: #b45309; }
        .badge-red { background: #fee2e2; color: #b91c1c; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }

        .footer-note {
            margin-top: 26px;
            font-size: 8px;
            color: #aaaaaa;
            text-align: center;
        }

        .text-right { text-align: right; }
        .text-muted { color: #999999; }

        /* `position: fixed` is dompdf's mechanism for an element that repeats on every page (the
           same trick used for running headers/footers) — placed once here, it shows up behind the
           content of every page a document spans, not just the first. Sized and centered for A4 at
           dompdf's ~96dpi baseline; opacity keeps it a background watermark rather than something
           that competes with the actual invoice/report text sitting on top of it. */
        .watermark {
            position: fixed;
            top: 380px;
            left: 187px;
            width: 420px;
            opacity: 0.05;
            z-index: -1;
        }
    </style>
</head>
<body>
    <img class="watermark" alt="" src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/branding/icon.png'))) }}">

    <div class="report-header">
        <div class="logo-cell">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/branding/icon.png'))) }}" width="42" height="35">
        </div>
        <div class="brand-cell">
            <div class="brand-name"><span class="china">China</span><span class="in">In</span><span class="nigeria">Nigeria</span></div>
            <div class="brand-tagline">Multi-Vendor Marketplace</div>
        </div>
    </div>

    <div class="report-meta">
        <div class="meta-left">Generated: {{ now()->format('d M Y, h:i A') }}</div>
        <div class="meta-right">Prepared for: {{ $preparedFor ?? 'ChinaInNigeria' }}</div>
    </div>

    <div class="report-title">{{ $title ?? 'Report' }}</div>
    @if (! empty($subtitle))
        <div class="report-subtitle">{{ $subtitle }}</div>
    @endif

    @yield('content')

    <div class="footer-note">
        This report was generated automatically by ChinaInNigeria and is confidential to the recipient named above.
    </div>
</body>
</html>
