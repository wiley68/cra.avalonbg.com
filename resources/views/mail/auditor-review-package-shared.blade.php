<x-mail::message>
# Review package shared

Hello {{ $auditor->name }},

**{{ $sharedByName }}** shared a compliance review package with auditors in **{{ $organizationName }}**.

- **Product:** {{ $product->name }}
- **Package:** {{ $package->title }}

@if($package->notes)
{{ $package->notes }}
@endif

<x-mail::button :url="$reviewUrl">
Open review package
</x-mail::button>

This is an automated notification from {{ config('app.name') }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
