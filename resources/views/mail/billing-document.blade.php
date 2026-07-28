<x-mail::message>
# {{ ucfirst($document->typeValue()) }} document

Hello,

Please find attached the **{{ $document->typeValue() }}** document for organization **{{ $organization->name }}**.

- **Title:** {{ $document->title }}
- **File:** {{ $document->source_filename }}

@if($document->notes)
{{ $document->notes }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
