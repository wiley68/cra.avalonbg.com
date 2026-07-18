@php
    use App\Support\Translations;

    $failCount = collect($report['gaps'])->where('status', 'fail')->count();
    $warnCount = collect($report['gaps'])->where('status', 'warn')->count();

    $statusLabel = static fn (string $status): string => Translations::get("products.readiness.status.{$status}");
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
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ Translations::get('products.readiness.title') }} — {{ $product['name'] }}</title>
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
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
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
        table.sections { width: 100%; border-collapse: collapse; }
        table.sections th,
        table.sections td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.sections th { background: #f9fafb; font-size: 10px; }
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
    </style>
</head>
<body>
    <h1>{{ Translations::get('products.readiness.title') }}</h1>
    <div class="meta">
        {{ $product['name'] }} ·
        {{ Translations::get('products.readiness.generated_at') }}:
        {{ $report['generated_at'] }}
    </div>

    <div class="disclaimer">
        {{ Translations::get('products.readiness.disclaimer') }}
    </div>

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
    <table class="sections">
        <thead>
            <tr>
                <th style="width: 28%;">{{ Translations::get('products.readiness.pdf.section') }}</th>
                <th style="width: 12%;">{{ Translations::get('products.readiness.pdf.status') }}</th>
                <th>{{ Translations::get('products.readiness.pdf.summary') }}</th>
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
