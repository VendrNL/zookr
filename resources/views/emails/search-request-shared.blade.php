<x-mail::message
    :mail-header-logo-url="$mailHeaderLogoUrl ?? null"
    :mail-header-logo-alt="$mailHeaderLogoAlt ?? null"
    :mail-header-website-url="$mailHeaderWebsiteUrl ?? null"
    :mail-header-website-label="$mailHeaderWebsiteLabel ?? null"
>
<style>
@media only screen and (max-width: 600px) {
    .sr-table,
    .sr-table tbody,
    .sr-table tr,
    .sr-table td {
        display: block !important;
        width: 100% !important;
    }

    .sr-table tr {
        padding-bottom: 8px !important;
    }

    .sr-label {
        padding: 4px 0 2px 0 !important;
    }

    .sr-value {
        padding: 0 0 6px 0 !important;
    }
}
</style>

Beste {{ $recipient->name }},

@php
    $propertyType = trim((string) ($searchRequest->property_type ?? ''));
    $propertyTypeLabel = $propertyType !== '' ? ucfirst(str_replace('_', ' ', $propertyType)) : '-';

    $acquisitions = collect($searchRequest->acquisitions ?? [])
        ->filter()
        ->map(function ($item) {
            $value = trim((string) $item);
            if ($value === 'koop') return 'Koop';
            if ($value === 'huur') return 'Huur';
            return ucfirst(str_replace('_', ' ', $value));
        })
        ->values()
        ->implode(', ');
@endphp

Namens {{ $searchRequest->customer_name ?: '-' }} zijn wij op zoek naar {{ $propertyTypeLabel }} dat voldoet aan de volgende eisen:

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="sr-table" style="margin: 12px 0 16px 0;">
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Locatie:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $searchRequest->location ?: '-' }}</td>
    </tr>
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Oppervlakte:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $searchRequest->surface_area ?: '-' }}</td>
    </tr>
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Parkeren:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $searchRequest->parking ?: '-' }}</td>
    </tr>
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Beschikbaarheid:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $searchRequest->availability ?: '-' }}</td>
    </tr>
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Bereikbaarheid:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $searchRequest->accessibility ?: '-' }}</td>
    </tr>
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Verwerving:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $acquisitions !== '' ? $acquisitions : '-' }}</td>
    </tr>
    <tr>
        <td valign="top" class="sr-label" style="width: 33%; padding: 4px 10px 4px 0; font-weight: 700; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">Bijzonderheden:</td>
        <td valign="top" class="sr-value" style="width: 67%; padding: 4px 0; color: #111827; word-break: normal; overflow-wrap: normal; hyphens: none;">{{ $searchRequest->notes ?: '-' }}</td>
    </tr>
</table>

<x-mail::button :url="route('search-requests.show', $searchRequest->id)">
Bied objecten aan via Zookr
</x-mail::button>

Met vriendelijke groet,

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
    <tr>
        <td valign="middle" style="width: 84px; padding-right: 14px;">
            @if(!empty($senderAvatarUrl))
                <img
                    src="{{ $senderAvatarUrl }}"
                    alt="{{ $senderName }}"
                    width="72"
                    height="72"
                    style="display:block; width:72px; height:72px; border-radius:9999px;"
                />
            @endif
        </td>
        <td valign="middle" style="line-height: 1.45; color: #111827;">
            <strong>{{ $senderName }}</strong><br>
            {{ $officeName }}<br>
            {{ $senderPhone ?? '-' }}<br>
            {{ $senderEmail ?? '-' }}
        </td>
    </tr>
</table>
</x-mail::message>
