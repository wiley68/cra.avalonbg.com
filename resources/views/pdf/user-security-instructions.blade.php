@php
    use App\Support\Translations;

    $statusKey = "products.user_security_instructions.statuses.{$instruction['status']}";
    $statusTranslated = Translations::get($statusKey);
    $statusLabel = $statusTranslated === $statusKey ? $instruction['status'] : $statusTranslated;

    $localeKey = "products.user_security_instructions.locales.{$instruction['locale']}";
    $localeTranslated = Translations::get($localeKey);
    $localeLabel = $localeTranslated === $localeKey ? $instruction['locale'] : $localeTranslated;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $instruction['locale'] ?: app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $instruction['title'] }} — {{ $instruction['version_label'] }}</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.5;
        }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 8px; page-break-after: avoid; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 12px; }
        .disclaimer {
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
            padding: 8px 10px;
            margin-bottom: 14px;
        }
        table.meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.meta-table th,
        table.meta-table td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.meta-table th {
            background: #f9fafb;
            font-size: 10px;
            width: 28%;
            color: #4b5563;
        }
        .section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .na {
            color: #6b7280;
            font-style: italic;
        }
        .body-content h1 { font-size: 16px; margin: 14px 0 8px; }
        .body-content h2 { font-size: 14px; margin: 12px 0 6px; }
        .body-content h3 { font-size: 12px; margin: 10px 0 6px; }
        .body-content p { margin: 0 0 8px; }
        .body-content ul, .body-content ol { margin: 0 0 8px; padding-left: 18px; }
        .body-content li { margin-bottom: 3px; }
        .body-content code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
            background: #f3f4f6;
            padding: 1px 3px;
        }
        .body-content pre {
            background: #f3f4f6;
            padding: 8px;
            margin: 0 0 8px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
        }
        .body-content blockquote {
            border-left: 3px solid #d1d5db;
            margin: 0 0 8px;
            padding: 4px 0 4px 10px;
            color: #4b5563;
        }
        .body-content a { color: #1d4ed8; text-decoration: underline; }
        .body-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 8px;
        }
        .body-content th,
        .body-content td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            text-align: left;
        }
    </style>
</head>
<body>
    <h1>{{ $instruction['title'] }}</h1>
    @if (!empty($customer))
        <div class="meta" style="font-weight: bold; color: #111827; margin-bottom: 6px;">
            {{ Translations::get('products.user_security_instructions.export.customer_guide_heading', [
                'customer' => $customer['name'],
            ]) }}
        </div>
    @endif
    <div class="meta">
        {{ $organization['name'] }} · {{ $product['name'] }} ·
        {{ Translations::get('products.user_security_instructions.export.generated_at') }}:
        {{ $generated_at }}
    </div>

    <div class="disclaimer">
        {{ Translations::get(
            !empty($customer)
                ? 'products.user_security_instructions.export.customer_disclaimer'
                : 'products.user_security_instructions.export.disclaimer'
        ) }}
    </div>

    <table class="meta-table">
        <tr>
            <th>{{ Translations::get('products.user_security_instructions.columns.status') }}</th>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.user_security_instructions.fields.product_version') }}</th>
            <td>
                {{ $instruction['product_version_number']
                    ?? Translations::get('products.user_security_instructions.product_wide') }}
            </td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.user_security_instructions.fields.version_label') }}</th>
            <td>{{ $instruction['version_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.user_security_instructions.fields.locale') }}</th>
            <td>{{ $localeLabel }}</td>
        </tr>
        @if (!empty($instruction['published_at']))
            <tr>
                <th>{{ Translations::get('products.user_security_instructions.fields.published_at') }}</th>
                <td>
                    {{ $instruction['published_at'] }}
                    @if (!empty($instruction['published_by_name']))
                        · {{ $instruction['published_by_name'] }}
                    @endif
                </td>
            </tr>
        @endif
        @if (!empty($customer))
            <tr>
                <th>{{ Translations::get('products.user_security_instructions.export.meta_customer') }}</th>
                <td>{{ $customer['name'] }}</td>
            </tr>
            @if (!empty($customer['external_ref']))
                <tr>
                    <th>{{ Translations::get('products.user_security_instructions.export.meta_customer_ref') }}</th>
                    <td>{{ $customer['external_ref'] }}</td>
                </tr>
            @endif
            @if (!empty($customer['primary_contact']))
                <tr>
                    <th>{{ Translations::get('products.user_security_instructions.export.meta_customer_contact') }}</th>
                    <td>{{ $customer['primary_contact'] }}</td>
                </tr>
            @endif
        @endif
        @if (!empty($deployment))
            <tr>
                <th>{{ Translations::get('products.user_security_instructions.export.meta_deployment_environment') }}</th>
                <td>{{ $deployment['environment'] }}</td>
            </tr>
            @if (!empty($deployment['product_version_number']))
                <tr>
                    <th>{{ Translations::get('products.user_security_instructions.export.meta_deployment_version') }}</th>
                    <td>{{ $deployment['product_version_number'] }}</td>
                </tr>
            @endif
            @if (!empty($deployment['update_channel']))
                <tr>
                    <th>{{ Translations::get('products.user_security_instructions.export.meta_update_channel') }}</th>
                    <td>{{ $deployment['update_channel'] }}</td>
                </tr>
            @endif
            @if (!empty($deployment['notes']))
                <tr>
                    <th>{{ Translations::get('products.user_security_instructions.export.meta_deployment_notes') }}</th>
                    <td>{{ $deployment['notes'] }}</td>
                </tr>
            @endif
        @endif
    </table>

    @foreach ($sections as $section)
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            @if (!$section['is_applicable'])
                <p class="na">{{ Translations::get('products.user_security_instructions.export.not_applicable') }}</p>
            @elseif ($section['body_html'] === '')
                <p class="na">{{ Translations::get('products.user_security_instructions.export.empty_section') }}</p>
            @else
                <div class="body-content">
                    {!! $section['body_html'] !!}
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
