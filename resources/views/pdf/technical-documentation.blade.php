@php
    use App\Support\Translations;

    $package = $package ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $package['locale'] ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ Translations::get('products.technical_documentation.export.title') }} — {{ $package['title'] }}</title>
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
        h3 { font-size: 12px; margin: 14px 0 6px; page-break-after: avoid; }
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
        .muted { color: #6b7280; }
        .block {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            margin-bottom: 8px;
            white-space: pre-wrap;
        }
        .body-content h1 { font-size: 16px; margin: 14px 0 8px; }
        .body-content h2 { font-size: 14px; margin: 12px 0 6px; }
        .body-content h3 { font-size: 12px; margin: 10px 0 6px; }
        .body-content h4 { font-size: 11px; margin: 10px 0 6px; }
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
    <h1>{{ $package['title'] }}</h1>
    <p class="meta">
        {{ $organization['name'] }} — {{ $product['name'] }} —
        {{ Translations::get('products.technical_documentation.export.generated_at') }}: {{ $generated_at }}
    </p>

    <div class="disclaimer">
        {{ Translations::get('products.technical_documentation.export.disclaimer') }}
    </div>

    <h2>{{ Translations::get('products.technical_documentation.export.section_overview') }}</h2>
    <table class="meta-table">
        <tr>
            <th>{{ Translations::get('products.technical_documentation.columns.status') }}</th>
            <td>{{ $package['status_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.technical_documentation.fields.version_label') }}</th>
            <td>{{ $package['version_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.technical_documentation.fields.product_version') }}</th>
            <td>
                {{ $package['product_version_number']
                    ?: Translations::get('products.technical_documentation.product_wide') }}
            </td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.technical_documentation.fields.locale') }}</th>
            <td>{{ $package['locale_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.technical_documentation.export.published_at') }}</th>
            <td>
                {{ $package['published_at'] ?: '—' }}
                @if (!empty($package['published_by_name']))
                    ({{ $package['published_by_name'] }})
                @endif
            </td>
        </tr>
    </table>

    @if (!empty($package['notes']))
        <h2>{{ Translations::get('products.technical_documentation.fields.notes') }}</h2>
        <div class="block">{{ $package['notes'] }}</div>
    @endif

    <h2>{{ Translations::get('products.technical_documentation.export.section_contents') }}</h2>
    @foreach ($package['sections'] as $section)
        <div class="section">
            <h3>{{ $section['title'] }}</h3>
            <p class="muted">
                {{ Translations::get('products.technical_documentation.export.source') }}:
                {{ $section['source_label'] }}
            </p>

            @if (empty($section['is_applicable']))
                <p class="na">{{ Translations::get('products.technical_documentation.not_applicable') }}</p>
                @if (!empty($section['override_reason']))
                    <p>
                        <strong>{{ Translations::get('products.technical_documentation.fields.override_reason') }}:</strong>
                        {{ $section['override_reason'] }}
                    </p>
                @endif
            @elseif ($section['body_html'] === '')
                <p class="muted">{{ Translations::get('products.technical_documentation.export.empty') }}</p>
            @else
                <div class="body-content">{!! $section['body_html'] !!}</div>
            @endif
        </div>
    @endforeach
</body>
</html>
