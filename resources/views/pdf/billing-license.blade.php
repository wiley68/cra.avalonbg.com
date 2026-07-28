@php
    use App\Support\Translations;

    $license = $license ?? [];
    $issuer = $issuer ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ Translations::get('billing.documents.license_pdf.title') }} — {{ $license['organization_name'] ?? '' }}</title>
    <style>
        @page { margin: 36px 40px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.45;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 6px;
        }
        .meta {
            color: #6b7280;
            font-size: 10px;
            margin-bottom: 18px;
        }
        .box {
            border: 1px solid #e5e7eb;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        .value {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .value:last-child { margin-bottom: 0; }
        .grid { width: 100%; }
        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 12px 0 0;
        }
        .statement {
            margin: 16px 0;
        }
        .disclaimer {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #4b5563;
            padding: 10px 12px;
            font-size: 9px;
            margin-top: 20px;
        }
        .ref {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <h1>{{ Translations::get('billing.documents.license_pdf.title') }}</h1>
    <p class="meta">
        {{ $issuer }} —
        {{ Translations::get('billing.documents.license_pdf.issued_at') }}: {{ $license['issued_at'] ?? '' }}
    </p>

    <div class="box">
        <table class="grid" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.licensee') }}</div>
                    <div class="value">{{ $license['organization_name'] ?? '' }}</div>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.org_slug') }}</div>
                    <div class="value">{{ $license['organization_slug'] ?? '' }}</div>
                </td>
                <td>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.reference') }}</div>
                    <div class="value ref">{{ $license['reference'] ?? '' }}</div>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.plan') }}</div>
                    <div class="value">{{ $license['plan_label'] ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="box">
        <table class="grid" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.interval') }}</div>
                    <div class="value">{{ $license['interval_label'] ?? '—' }}</div>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.payment_method') }}</div>
                    <div class="value">{{ $license['payment_method_label'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.billing_status') }}</div>
                    <div class="value">{{ $license['billing_status_label'] ?? '' }}</div>
                    <div class="label">{{ Translations::get('billing.documents.license_pdf.activated_at') }}</div>
                    <div class="value">{{ $license['activated_at'] ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <p class="statement">
        {{ Translations::get('billing.documents.license_pdf.statement', [
            'organization' => $license['organization_name'] ?? '',
            'plan' => $license['plan_label'] ?? '',
            'product' => $issuer,
        ]) }}
    </p>

    <div class="disclaimer">
        {{ Translations::get('billing.documents.license_pdf.disclaimer') }}
    </div>
</body>
</html>
