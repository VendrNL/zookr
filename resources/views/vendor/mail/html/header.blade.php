@props([
    'url',
    'mailHeaderLogoUrl' => null,
    'mailHeaderLogoAlt' => null,
    'mailHeaderWebsiteUrl' => null,
    'mailHeaderWebsiteLabel' => null,
])
<tr>
<td class="header">
    @php
        $headerLogoUrl = $mailHeaderLogoUrl ?? asset('assets/images/Zookr.svg');
        $headerLogoAlt = $mailHeaderLogoAlt ?? 'Zookr';
        $headerLogoUrl = (str_starts_with($headerLogoUrl, 'http://') || str_starts_with($headerLogoUrl, 'https://'))
            ? $headerLogoUrl
            : url($headerLogoUrl);

        $officeWebsiteUrl = is_string($mailHeaderWebsiteUrl) ? trim($mailHeaderWebsiteUrl) : '';
        if ($officeWebsiteUrl !== '' && ! str_starts_with($officeWebsiteUrl, 'http://') && ! str_starts_with($officeWebsiteUrl, 'https://')) {
            $officeWebsiteUrl = 'https://' . ltrim($officeWebsiteUrl, '/');
        }
        $headerLinkUrl = $officeWebsiteUrl !== '' ? $officeWebsiteUrl : $url;
    @endphp
<a href="{{ $headerLinkUrl }}" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none;">
    <img src="{{ $headerLogoUrl }}" class="logo" alt="{{ $headerLogoAlt }}">
</a>
</td>
</tr>
