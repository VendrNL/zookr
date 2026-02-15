<x-mail::layout>
@php
    $headerLogoFromAttributes = $attributes->get('mail-header-logo-url');
    $headerLogoAltFromAttributes = $attributes->get('mail-header-logo-alt');
@endphp
{{-- Header --}}
<x-slot:header>
<x-mail::header
    :url="config('app.url')"
    :mail-header-logo-url="$headerLogoFromAttributes ?? ($mailHeaderLogoUrl ?? null)"
    :mail-header-logo-alt="$headerLogoAltFromAttributes ?? ($mailHeaderLogoAlt ?? null)"
>
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} RESAAS. Alle rechten voorbehouden.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
