@php
    use App\Support\Translations;

    $rows = $rows ?? [];
    $counts = $counts ?? ['total' => 0, 'failed' => 0, 'soft_fail' => 0, 'never' => 0, 'ok' => 0];
    $organization = $organization ?? ['name' => '', 'slug' => ''];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ Translations::get('integrations.health.export.title') }} — {{ $organization['name'] }}</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            line-height: 1.4;
        }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 14px 0 8px; }
        .meta { color: #6b7280; font-size: 9px; margin-bottom: 10px; }
        .disclaimer {
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }
        th { background: #f9fafb; font-size: 9px; }
        .muted { color: #6b7280; }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-failed { background: #dc2626; color: #fff; }
        .badge-soft_fail { background: #f59e0b; color: #fff; }
        .badge-never { background: #e5e7eb; color: #374151; }
        .badge-ok { background: #059669; color: #fff; }
        .counts td { width: 20%; }
    </style>
</head>
<body>
    <h1>{{ Translations::get('integrations.health.export.title') }}</h1>
    <p class="meta">
        {{ $organization['name'] }} —
        {{ Translations::get('integrations.health.export.generated_at') }}: {{ $generated_at }}
    </p>

    <div class="disclaimer">
        {{ Translations::get('integrations.health.export.disclaimer') }}
    </div>

    <h2>{{ Translations::get('integrations.health.export.section_summary') }}</h2>
    <table class="counts">
        <tr>
            <th>{{ Translations::get('integrations.health.export.row_count') }}</th>
            <th>{{ Translations::get('integrations.health.status.failed') }}</th>
            <th>{{ Translations::get('integrations.health.status.soft_fail') }}</th>
            <th>{{ Translations::get('integrations.health.status.never') }}</th>
            <th>{{ Translations::get('integrations.health.status.ok') }}</th>
        </tr>
        <tr>
            <td>{{ $counts['total'] }}</td>
            <td>{{ $counts['failed'] }}</td>
            <td>{{ $counts['soft_fail'] }}</td>
            <td>{{ $counts['never'] }}</td>
            <td>{{ $counts['ok'] }}</td>
        </tr>
    </table>

    <h2>{{ Translations::get('integrations.health.export.section_rows') }}</h2>
    @if ($rows === [])
        <p class="muted">{{ Translations::get('integrations.health.export.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ Translations::get('integrations.health.columns.provider') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.product') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.target') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.connection_status') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.last_synced_at') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.health') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.last_error') }}</th>
                    <th>{{ Translations::get('integrations.health.columns.pending_suggestions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['provider'] }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['target'] }}</td>
                        <td>{{ $row['connection_status'] }}</td>
                        <td>{{ $row['last_synced_at'] ?: '—' }}</td>
                        <td>
                            <span class="badge badge-{{ $row['health'] }}">
                                {{ $row['health_label'] }}
                            </span>
                        </td>
                        <td>{{ $row['last_error'] ?: '—' }}</td>
                        <td>{{ $row['pending_suggestions'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
