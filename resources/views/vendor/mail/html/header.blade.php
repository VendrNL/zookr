@props([
    'url',
    'mailHeaderLogoUrl' => null,
    'mailHeaderLogoAlt' => null,
])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
    @php
        $headerLogoUrl = $mailHeaderLogoUrl ?? asset('assets/images/Zookr.svg');
        $headerLogoAlt = $mailHeaderLogoAlt ?? 'Zookr';
        $headerLogoUrl = (str_starts_with($headerLogoUrl, 'http://') || str_starts_with($headerLogoUrl, 'https://'))
            ? $headerLogoUrl
            : url($headerLogoUrl);
    @endphp
    <img src="{{ $headerLogoUrl }}" class="logo" alt="{{ $headerLogoAlt }}">
</a>
</td>
</tr>
