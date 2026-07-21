<x-mail::message>
# Security update available

Hello {{ $customerName }},

This is a notification from **{{ $product->name }}** regarding campaign **{{ $campaign->title }}**.

- **Environment:** {{ $environment }}
- **Current version:** {{ $currentVersionNumber ?? 'unknown' }}
- **Target version:** {{ $targetVersionNumber }}

Please apply the update and confirm with your vendor contact when complete.

@if($campaign->notes)
{{ $campaign->notes }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
