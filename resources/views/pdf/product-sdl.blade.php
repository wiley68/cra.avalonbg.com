@php
    use App\Support\Translations;

    $run = $run ?? [];
    $textOrEmpty = static function (?string $value): string {
        return filled($value)
            ? e($value)
            : e(Translations::get('products.sdl.export.empty'));
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ Translations::get('products.sdl.export.title') }} — {{ $run['title'] }}</title>
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
        h3 { font-size: 12px; margin: 14px 0 6px; }
        h4 { font-size: 11px; margin: 10px 0 4px; }
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
        .stage {
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <h1>{{ $run['title'] }}</h1>
    <p class="meta">
        {{ $organization['name'] }} — {{ $product['name'] }} —
        {{ Translations::get('products.sdl.export.generated_at') }}: {{ $generated_at }}
    </p>

    <div class="disclaimer">
        {{ Translations::get('products.sdl.export.disclaimer') }}
    </div>

    <h2>{{ Translations::get('products.sdl.export.section_overview') }}</h2>
    <table class="meta-table">
        <tr>
            <th>{{ Translations::get('products.sdl.fields.status') }}</th>
            <td>{{ $run['status_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.sdl.fields.current_stage') }}</th>
            <td>{{ $run['current_stage_label'] }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.sdl.fields.product_version') }}</th>
            <td>{{ $run['version_number'] ?: Translations::get('products.sdl.version_none') }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.sdl.fields.owner') }}</th>
            <td>{{ $run['owner_name'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.sdl.export.approved_at') }}</th>
            <td>
                {{ $run['approved_at'] ?: '—' }}
                @if (!empty($run['approved_by_name']))
                    ({{ $run['approved_by_name'] }})
                @endif
            </td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.sdl.fields.linked_usi') }}</th>
            <td>
                @if (!empty($run['linked_usi']))
                    {{ $run['linked_usi']['title'] }}
                    ({{ $run['linked_usi']['version_label'] }}, {{ $run['linked_usi']['locale'] }})
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <th>{{ Translations::get('products.sdl.fields.tech_doc_delta_reviewed') }}</th>
            <td>
                {{ !empty($run['tech_doc_delta_reviewed'])
                    ? Translations::get('common.yes')
                    : Translations::get('common.no') }}
            </td>
        </tr>
    </table>

    @if (!empty($run['notes']))
        <h2>{{ Translations::get('products.sdl.fields.notes') }}</h2>
        <div class="block">{{ $run['notes'] }}</div>
    @endif

    <h2>{{ Translations::get('products.sdl.export.section_stages') }}</h2>
    @foreach ($run['stages'] as $stage)
        <div class="stage">
            <h3>{{ $stage['stage_label'] }}</h3>
            <table class="meta-table">
                <tr>
                    <th>{{ Translations::get('products.sdl.fields.stage_status') }}</th>
                    <td>{{ $stage['status_label'] }}</td>
                </tr>
                <tr>
                    <th>{{ Translations::get('products.sdl.export.completed_at') }}</th>
                    <td>
                        {{ $stage['completed_at'] ?: '—' }}
                        @if (!empty($stage['completed_by_name']))
                            ({{ $stage['completed_by_name'] }})
                        @endif
                    </td>
                </tr>
            </table>

            <h4>{{ Translations::get('products.sdl.fields.stage_notes') }}</h4>
            <div class="block">{!! $textOrEmpty($stage['notes'] ?? null) !!}</div>

            <h4>{{ Translations::get('products.sdl.export.stage_evidence') }}</h4>
            @if (empty($stage['evidence']))
                <p class="muted">{{ Translations::get('products.sdl.export.empty') }}</p>
            @else
                <ul>
                    @foreach ($stage['evidence'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($stage['exception']))
                <h4>{{ Translations::get('products.sdl.export.section_exception') }}</h4>
                <table class="meta-table">
                    <tr>
                        <th>{{ Translations::get('products.sdl.fields.exception_owner') }}</th>
                        <td>{{ $stage['exception']['owner_name'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ Translations::get('products.sdl.fields.exception_expires_at') }}</th>
                        <td>
                            {{ $stage['exception']['expires_at'] }}
                            @if (!empty($stage['exception']['is_expired']))
                                — {{ Translations::get('products.sdl.exception_expired') }}
                            @endif
                        </td>
                    </tr>
                </table>
            @endif
        </div>
    @endforeach

    <h2>{{ Translations::get('products.sdl.export.section_run_evidence') }}</h2>
    @if (empty($run['evidence']))
        <p class="muted">{{ Translations::get('products.sdl.export.empty') }}</p>
    @else
        <ul>
            @foreach ($run['evidence'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
