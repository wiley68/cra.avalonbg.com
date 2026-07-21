@php
    use App\Support\Translations;

    $failCount = collect($report['gaps'])->where('status', 'fail')->count();
    $warnCount = collect($report['gaps'])->where('status', 'warn')->count();

    $statusLabel = static fn (string $status): string => Translations::get("products.readiness.status.{$status}");
    $packageStatus = static function (string $status): string {
        $key = "auditor.statuses.{$status}";
        $translated = Translations::get($key);

        return $translated === $key ? $status : $translated;
    };
    $findingSeverity = static function (string $severity): string {
        $key = "auditor.findings.severities.{$severity}";
        $translated = Translations::get($key);

        return $translated === $key ? $severity : $translated;
    };
    $findingStatus = static function (string $status): string {
        $key = "auditor.findings.statuses.{$status}";
        $translated = Translations::get($key);

        return $translated === $key ? $status : $translated;
    };
    $sectionTitle = static function (string $key): string {
        $translated = Translations::get("products.readiness.sections.{$key}");

        return $translated === "products.readiness.sections.{$key}" ? $key : $translated;
    };
    $summaryLabel = static function (array $section): string {
        $key = "products.readiness.summaries.{$section['key']}.{$section['summary']}";
        $translated = Translations::get($key);

        return $translated === $key ? (string) $section['summary'] : $translated;
    };
    $gapMessage = static function (array $gap): string {
        $translated = Translations::get($gap['message_key']);

        return $translated === $gap['message_key']
            ? Translations::get('products.readiness.gaps.generic')
            : $translated;
    };
    $enumLabel = static function (string $group, ?string $value): string {
        if ($value === null || $value === '') {
            return Translations::get('products.passport.empty');
        }
        $key = "products.{$group}.{$value}";
        $translated = Translations::get($key);

        return $translated === $key ? $value : $translated;
    };
    $evidenceType = static function (string $type): string {
        $key = "products.evidence.types.{$type}";
        $translated = Translations::get($key);

        return $translated === $key ? $type : $translated;
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $package['title'] }}</title>
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
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 12px; }
        .disclaimer {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            color: #374151;
            padding: 8px 10px;
            margin-bottom: 14px;
        }
        .metrics { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .metrics td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            width: 33%;
            vertical-align: top;
        }
        .metrics .label { color: #6b7280; font-size: 9px; display: block; margin-bottom: 2px; }
        .metrics .value { font-size: 16px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.data th,
        table.data td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.data th { background: #f9fafb; font-size: 10px; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pass { background: #dcfce7; color: #166534; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-na { background: #f3f4f6; color: #4b5563; }
        ul.gaps { padding-left: 16px; margin: 0; }
        ul.gaps li { margin-bottom: 4px; }
        .notes { white-space: pre-wrap; margin: 0; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $package['title'] }}</h1>
    <div class="meta">
        {{ $organization['name'] }} · {{ $product['name'] }} ·
        {{ $packageStatus($package['status']) }} ·
        {{ Translations::get('auditor.export.generated_at') }}:
        {{ $generated_at }}
    </div>

    <div class="disclaimer">
        {{ Translations::get('auditor.export.disclaimer') }}
    </div>

    @if (!empty($package['notes']))
        <h2>{{ Translations::get('auditor.fields.notes') }}</h2>
        <p class="notes">{{ $package['notes'] }}</p>
    @endif

    <h2>{{ Translations::get('auditor.findings.title') }}</h2>
    @if (count($findings) === 0)
        <p class="muted">{{ Translations::get('auditor.findings.empty') }}</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>{{ Translations::get('auditor.findings.fields.title') }}</th>
                    <th>{{ Translations::get('auditor.findings.fields.severity') }}</th>
                    <th>{{ Translations::get('auditor.findings.fields.status') }}</th>
                    <th>{{ Translations::get('auditor.findings.fields.body') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($findings as $finding)
                    <tr>
                        <td>{{ $finding['title'] }}</td>
                        <td>{{ $findingSeverity($finding['severity']) }}</td>
                        <td>{{ $findingStatus($finding['status']) }}</td>
                        <td>{{ $finding['body'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ Translations::get('auditor.fields.evidence') }}</h2>
    @if (count($evidence) === 0)
        <p class="muted">{{ Translations::get('auditor.no_package_evidence') }}</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>{{ Translations::get('auditor.findings.fields.title') }}</th>
                    <th>{{ Translations::get('products.evidence.fields.type') }}</th>
                    <th>{{ Translations::get('auditor.export.file_included') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($evidence as $item)
                    <tr>
                        <td>{{ $item['title'] }}</td>
                        <td>{{ $evidenceType($item['type']) }}</td>
                        <td>
                            {{ $item['has_file']
                                ? Translations::get('common.yes')
                                : Translations::get('common.no') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ Translations::get('products.passport.identity_title') }}</h2>
    <table class="data">
        <tbody>
            <tr>
                <th>{{ Translations::get('products.fields.manufacturer') }}</th>
                <td>{{ $product['manufacturer'] ?: Translations::get('products.passport.empty') }}</td>
                <th>{{ Translations::get('products.fields.product_type') }}</th>
                <td>{{ $enumLabel('types', $product['product_type']) }}</td>
            </tr>
            <tr>
                <th>{{ Translations::get('products.passport.scope') }}</th>
                <td>{{ $enumLabel('scope', $product['scope_status']) }}</td>
                <th>{{ Translations::get('products.passport.classification') }}</th>
                <td>{{ $enumLabel('classification', $product['classification_status']) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>{{ Translations::get('products.readiness.title') }}</h2>
    <table class="metrics">
        <tr>
            <td>
                <span class="label">{{ Translations::get('products.readiness.metrics.failures') }}</span>
                <span class="value">{{ $failCount }}</span>
            </td>
            <td>
                <span class="label">{{ Translations::get('products.readiness.metrics.warnings') }}</span>
                <span class="value">{{ $warnCount }}</span>
            </td>
            <td>
                <span class="label">{{ Translations::get('products.readiness.metrics.generated') }}</span>
                <span class="value" style="font-size: 11px;">{{ $report['generated_at'] }}</span>
            </td>
        </tr>
    </table>

    @if (count($report['gaps']) > 0)
        <h2>{{ Translations::get('products.readiness.gaps_title') }}</h2>
        <ul class="gaps">
            @foreach ($report['gaps'] as $gap)
                <li>
                    <span class="badge badge-{{ $gap['status'] }}">{{ $statusLabel($gap['status']) }}</span>
                    {{ $gapMessage($gap) }}
                </li>
            @endforeach
        </ul>
    @endif

    <h2>{{ Translations::get('products.readiness.sections_title') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ Translations::get('products.readiness.sections_title') }}</th>
                <th>{{ Translations::get('auditor.fields.status') }}</th>
                <th>{{ Translations::get('auditor.findings.fields.body') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['sections'] as $section)
                <tr>
                    <td>{{ $sectionTitle($section['key']) }}</td>
                    <td>
                        <span class="badge badge-{{ $section['status'] }}">
                            {{ $statusLabel($section['status']) }}
                        </span>
                    </td>
                    <td>{{ $summaryLabel($section) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
