@php
    use App\Support\Translations;

    $incident = $incident ?? [];
    $textOrEmpty = static function (?string $value): string {
        return filled($value)
            ? e($value)
            : e(Translations::get('products.incidents.export.empty'));
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ Translations::get('products.incidents.export.title') }} — {{ $incident['title'] }}</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.45;
        }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        h3 { font-size: 11px; margin: 12px 0 4px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 12px; }
        .disclaimer {
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
            padding: 8px 10px;
            margin-bottom: 14px;
        }
        table.meta-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.meta-table th,
        table.meta-table td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.meta-table th { width: 30%; background: #f9fafb; font-size: 10px; }
        .block {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            margin-bottom: 8px;
            white-space: pre-wrap;
        }
        ul { padding-left: 16px; margin: 0 0 8px; }
        .muted { color: #6b7280; }
        .timeline { width: 100%; border-collapse: collapse; }
        .timeline th,
        .timeline td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        .timeline th { background: #f9fafb; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $incident['title'] }}</h1>
    <p class="meta">
        {{ $organization['name'] }} — {{ $product['name'] }} —
        {{ Translations::get('products.incidents.export.generated_at') }}: {{ $generated_at }}
    </p>

    <div class="disclaimer">
        {{ Translations::get('products.incidents.export.disclaimer') }}
    </div>

    <h2>{{ Translations::get('products.incidents.export.section_overview') }}</h2>
    <table class="meta-table">
        <tr>
            <th>{{ Translations::get('products.incidents.fields.status') }}</th>
            <td>{{ $incident['status_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.severity') }}</th>
            <td>{{ $incident['severity_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.owner') }}</th>
            <td>{{ $incident['owner_name'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.actual_started_at') }}</th>
            <td>{{ $incident['actual_started_at'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.detected_at') }}</th>
            <td>{{ $incident['detected_at'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.awareness_at') }}</th>
            <td>{{ $incident['awareness_at'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.classified_at') }}</th>
            <td>{{ $incident['classified_at'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.incidents.fields.closed_at') }}</th>
            <td>
                {{ $incident['closed_at'] ?: '—' }}
                @if (!empty($incident['closed_by_name']))
                    ({{ $incident['closed_by_name'] }})
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ Translations::get('products.incidents.fields.summary') }}</h2>
    <div class="block">{!! $textOrEmpty($incident['summary'] ?? null) !!}</div>

    <h2>{{ Translations::get('products.incidents.investigation_title') }}</h2>
    <h3>{{ Translations::get('products.incidents.fields.root_cause') }}</h3>
    <div class="block">{!! $textOrEmpty($incident['root_cause'] ?? null) !!}</div>
    <h3>{{ Translations::get('products.incidents.fields.corrective_measures') }}</h3>
    <div class="block">{!! $textOrEmpty($incident['corrective_measures'] ?? null) !!}</div>
    <h3>{{ Translations::get('products.incidents.fields.lessons_learned') }}</h3>
    <div class="block">{!! $textOrEmpty($incident['lessons_learned'] ?? null) !!}</div>

    @if (!empty($incident['notes']))
        <h2>{{ Translations::get('products.incidents.fields.notes') }}</h2>
        <div class="block">{{ $incident['notes'] }}</div>
    @endif

    <h2>{{ Translations::get('products.incidents.fields.versions') }}</h2>
    @if (empty($incident['versions']))
        <p class="muted">{{ Translations::get('products.incidents.export.empty') }}</p>
    @else
        <ul>
            @foreach ($incident['versions'] as $version)
                <li>{{ $version }}</li>
            @endforeach
        </ul>
    @endif

    <h2>{{ Translations::get('products.incidents.fields.customers') }}</h2>
    @if (empty($incident['customers']))
        <p class="muted">{{ Translations::get('products.incidents.export.empty') }}</p>
    @else
        <ul>
            @foreach ($incident['customers'] as $customer)
                <li>{{ $customer }}</li>
            @endforeach
        </ul>
    @endif

    <h2>{{ Translations::get('products.incidents.fields.deployments') }}</h2>
    @if (empty($incident['deployments']))
        <p class="muted">{{ Translations::get('products.incidents.export.empty') }}</p>
    @else
        <ul>
            @foreach ($incident['deployments'] as $deployment)
                <li>{{ $deployment }}</li>
            @endforeach
        </ul>
    @endif

    <h2>{{ Translations::get('products.incidents.vulnerability_title') }}</h2>
    @if (empty($incident['linked_vulnerability']))
        <p class="muted">{{ Translations::get('products.incidents.export.empty') }}</p>
    @else
        <p>
            {{ $incident['linked_vulnerability']['title'] }}
            @if (!empty($incident['linked_vulnerability']['cve_id']))
                ({{ $incident['linked_vulnerability']['cve_id'] }})
            @endif
            — {{ $incident['linked_vulnerability']['status'] }}
        </p>
    @endif

    <h2>{{ Translations::get('products.incidents.timeline_title') }}</h2>
    @if (empty($incident['timeline_events']))
        <p class="muted">{{ Translations::get('products.incidents.export.empty') }}</p>
    @else
        <table class="timeline">
            <thead>
                <tr>
                    <th>{{ Translations::get('products.incidents.fields.timeline_occurred_at') }}</th>
                    <th>{{ Translations::get('products.incidents.fields.timeline_label') }}</th>
                    <th>{{ Translations::get('products.incidents.fields.timeline_notes') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incident['timeline_events'] as $event)
                    <tr>
                        <td>{{ $event['occurred_at'] }}</td>
                        <td>
                            {{ $event['label'] }}
                            @if (!empty($event['created_by']))
                                <div class="muted">{{ $event['created_by'] }}</div>
                            @endif
                        </td>
                        <td>{{ $event['notes'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
