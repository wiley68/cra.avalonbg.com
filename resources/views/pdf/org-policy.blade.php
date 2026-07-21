@php
    use App\Support\Translations;

    $typeKey = "policies.types.{$policy['policy_type']}";
    $typeTranslated = Translations::get($typeKey);
    $typeLabel = $typeTranslated === $typeKey ? $policy['policy_type'] : $typeTranslated;

    $statusKey = "policies.statuses.{$policy['status']}";
    $statusTranslated = Translations::get($statusKey);
    $statusLabel = $statusTranslated === $statusKey ? $policy['status'] : $statusTranslated;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $policy['title'] }} — {{ $policy['version_label'] }}</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.5;
        }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 8px; }
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
    <h1>{{ $policy['title'] }}</h1>
    <div class="meta">
        {{ $organization['name'] }} ·
        {{ Translations::get('policies.pdf.generated_at') }}:
        {{ $generated_at }}
    </div>

    <div class="disclaimer">
        {{ Translations::get('policies.pdf.disclaimer') }}
    </div>

    <table class="meta-table">
        <tr>
            <th>{{ Translations::get('policies.fields.policy_type') }}</th>
            <td>{{ $typeLabel }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('policies.columns.status') }}</th>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('policies.fields.version_label') }}</th>
            <td>{{ $policy['version_label'] }}</td>
        </tr>
        @if (!empty($policy['approved_at']))
            <tr>
                <th>{{ Translations::get('policies.fields.approved_at') }}</th>
                <td>
                    {{ $policy['approved_at'] }}
                    @if (!empty($policy['approved_by_name']))
                        · {{ $policy['approved_by_name'] }}
                    @endif
                </td>
            </tr>
        @endif
    </table>

    <h2>{{ Translations::get('policies.pdf.body_title') }}</h2>
    <div class="body-content">
        {!! $policy['body_html'] !!}
    </div>
</body>
</html>
