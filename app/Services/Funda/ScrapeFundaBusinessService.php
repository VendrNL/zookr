<?php

namespace App\Services\Funda;

use App\Models\Property;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ScrapeFundaBusinessService
{
    public function __construct(private readonly FundaBusinessMapper $mapper)
    {
    }

    /**
     * @return array{payload: array<string, mixed>, property: ?Property, scraped: array<string, mixed>}
     */
    public function import(string $url, array $context, bool $dryRun = false, bool $throttle = true): array
    {
        $scraped = $this->scrape($url, $throttle);
        $payload = $this->mapScraped($scraped, $context);

        if (! $dryRun) {
            if ($scraped['brochure_url']) {
                $payload['brochure_path'] = $this->downloadFile($scraped['brochure_url'], 'brochures', $scraped['external_id']);
            }

            if ($scraped['drawing_urls'] !== []) {
                $payload['drawings'] = $this->downloadFiles($scraped['drawing_urls'], 'drawings', $scraped['external_id']);
            }
        }

        $property = null;
        if (! $dryRun) {
            $property = Property::updateOrCreate(['url' => $url], $payload);
        }

        return [
            'payload' => $payload,
            'property' => $property,
            'scraped' => $scraped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scrape(string $url, bool $throttle = true): array
    {
        $this->validateUrl($url);

        if ($throttle) {
            usleep(1_000_000);
        }

        $response = $this->http()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("Funda Business request failed with status {$response->status()}");
        }

        return $this->parseDocument($response->body(), $url, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function scrapeFromHtml(string $html, ?string $url = null): array
    {
        $sourceUrl = $url ?: 'https://www.fundainbusiness.nl/';

        return $this->parseDocument($html, $sourceUrl, false);
    }

    public function mapScraped(array $scraped, array $context): array
    {
        $mapped = $this->mapper->map($scraped, $context);

        return $mapped['payload'];
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => 'ZookrFundaBusinessBot/1.0 (+https://zookr.local)',
            'Accept-Language' => 'nl-NL,nl;q=0.9',
        ])->retry(3, 500);
    }

    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if ($host === '' || ! preg_match('/(^|\\.)fundainbusiness\\.nl$/', $host)) {
            throw new RuntimeException('URL must be on fundainbusiness.nl.');
        }
    }

    private function extractExternalId(string $url): ?string
    {
        if (preg_match('/(object-\\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDocument(string $html, string $url, bool $requireExternalId): array
    {
        [, $xpath] = $this->loadDocument($html);

        $externalId = $this->extractExternalId($url);
        if ($requireExternalId && $externalId === null) {
            throw new RuntimeException('Unable to extract external_id from URL.');
        }

        $title = $this->extractTitle($xpath);
        $featuresPayload = $this->extractFeatures($xpath);
        $features = $featuresPayload['features'];
        $rawFeatures = $featuresPayload['raw_features'];

        $pricingDisplay = $this->extractPricingDisplay($xpath);
        $addressLine = $this->extractAddressLine($xpath, $title, $features);
        $city = $this->extractCity($title, $addressLine, $features);

        $broker = $this->extractBroker($xpath, $features);
        $neighborhood = $this->extractNeighborhood($features);

        return [
            'url' => $url,
            'external_id' => $externalId,
            'scraped_at' => now()->toIso8601String(),
            'title' => $title,
            'address_line' => $addressLine,
            'city' => $city,
            'pricing_display' => $pricingDisplay,
            'broker' => $broker,
            'neighborhood' => $neighborhood,
            'features' => $features,
            'raw_features' => $rawFeatures,
            'images' => $this->extractImages($xpath, $url),
            'brochure_url' => $this->extractBrochureUrl($xpath, $url),
            'drawing_urls' => $this->extractDrawingUrls($xpath, $url),
        ];
    }

    private function extractTitle(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//h1');
        if ($nodes->length > 0) {
            $text = trim((string) $nodes->item(0)->textContent);
            if ($text !== '') {
                return $text;
            }
        }

        $metaNodes = $xpath->query("//meta[@property='og:title']/@content");
        if ($metaNodes->length > 0) {
            $text = trim((string) $metaNodes->item(0)->nodeValue);
            return $text !== '' ? $text : null;
        }

        return null;
    }

    private function extractPricingDisplay(\DOMXPath $xpath): ?string
    {
        $textNodes = $xpath->query("//text()[contains(., '€') or contains(translate(., 'OP AANVRAAG', 'op aanvraag'), 'op aanvraag')]");
        foreach ($textNodes as $node) {
            $value = trim(preg_replace('/\\s+/', ' ', (string) $node->nodeValue) ?? '');
            if ($value !== '' && strlen($value) <= 200) {
                return $value;
            }
        }

        return null;
    }

    private function extractAddressLine(\DOMXPath $xpath, ?string $title, array $features): ?string
    {
        $address = $this->findFeatureValue($features, ['adres'], ['adres', 'straat']);
        $postcode = $this->findPostcode($xpath);

        $base = $address ?? $title;
        if ($base === null) {
            return null;
        }

        if ($postcode !== null && ! str_contains($base, $postcode)) {
            return trim($base.' '.$postcode);
        }

        return $base;
    }

    private function extractCity(?string $title, ?string $addressLine, array $features): ?string
    {
        $city = $this->findFeatureValue($features, ['adres', 'algemeen', 'overig'], ['plaats', 'stad']);
        if ($city !== null) {
            return $city;
        }

        $source = $addressLine ?? $title;
        if ($source !== null && preg_match('/,\\s*([^,]+)$/', $source, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractBroker(\DOMXPath $xpath, array $features): array
    {
        $name = $this->findFeatureValue($features, ['algemeen', 'overig'], ['aangeboden_door', 'aanbieder', 'makelaar']);

        if ($name === null) {
            $bodyText = $this->getBodyText($xpath);
            if (preg_match('/Aangeboden door\\s+([^\\n\\r]+)/i', $bodyText, $matches)) {
                $name = trim($matches[1]);
            }
        }

        $phone = $this->extractPhone($xpath);

        return [
            'name' => $name,
            'phone' => $phone,
        ];
    }

    private function extractPhone(\DOMXPath $xpath): ?string
    {
        $bodyText = $this->getBodyText($xpath);
        if (preg_match('/\\+?\\d[\\d\\s\\-()]{6,}\\d/', $bodyText, $matches)) {
            return FundaBusinessNormalizer::digitsOnly($matches[0]);
        }

        return null;
    }

    private function extractNeighborhood(array $features): ?string
    {
        return $this->findFeatureValue($features, ['omgeving', 'algemeen', 'overig'], ['buurt', 'wijk']);
    }

    private function extractFeatures(\DOMXPath $xpath): array
    {
        $features = [];
        $rawFeatures = [];

        foreach ($xpath->query('//dl') as $dlNode) {
            $sectionTitle = $this->findSectionTitle($dlNode) ?? 'Kenmerken';
            $sectionSlug = FundaBusinessNormalizer::slugify($sectionTitle);

            $dtNodes = $xpath->query('dt', $dlNode);
            $ddNodes = $xpath->query('dd', $dlNode);
            $count = min($dtNodes->length, $ddNodes->length);

            for ($i = 0; $i < $count; $i++) {
                $label = trim(preg_replace('/\\s+/', ' ', (string) $dtNodes->item($i)->textContent) ?? '');
                $value = trim(preg_replace('/\\s+/', ' ', (string) $ddNodes->item($i)->textContent) ?? '');

                if ($label === '' || $value === '') {
                    continue;
                }

                $labelSlug = FundaBusinessNormalizer::slugify($label);
                $keyBase = "features.{$sectionSlug}.{$labelSlug}";
                $key = $keyBase;
                $suffix = 2;
                while (array_key_exists($key, $rawFeatures)) {
                    $key = "{$keyBase}_{$suffix}";
                    $suffix++;
                }

                $rawFeatures[$key] = $value;
                $features[] = [
                    'section' => $sectionTitle,
                    'section_slug' => $sectionSlug,
                    'label' => $label,
                    'label_slug' => $labelSlug,
                    'value' => $value,
                    'raw_key' => $key,
                ];
            }
        }

        return [
            'features' => $features,
            'raw_features' => $rawFeatures,
        ];
    }

    private function findSectionTitle(\DOMElement $dlNode): ?string
    {
        $node = $dlNode->previousSibling;
        while ($node !== null) {
            if ($node instanceof \DOMElement && in_array($node->tagName, ['h2', 'h3'], true)) {
                $text = trim(preg_replace('/\\s+/', ' ', $node->textContent) ?? '');
                if ($text !== '') {
                    return $text;
                }
            }
            $node = $node->previousSibling;
        }

        return null;
    }

    private function findFeatureValue(array $features, array $sectionSlugs, array $labelSlugs): ?string
    {
        foreach ($features as $feature) {
            if (! in_array($feature['section_slug'], $sectionSlugs, true)) {
                continue;
            }

            foreach ($labelSlugs as $label) {
                if ($feature['label_slug'] === $label || str_contains($feature['label_slug'], $label)) {
                    return $feature['value'];
                }
            }
        }

        return null;
    }

    private function findPostcode(\DOMXPath $xpath): ?string
    {
        $text = $this->getBodyText($xpath);
        if (preg_match('/\\b\\d{4}\\s?[A-Z]{2}\\b/', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function extractImages(\DOMXPath $xpath, string $baseUrl): array
    {
        $urls = [];

        foreach ($xpath->query('//img') as $imgNode) {
            $srcset = $imgNode->getAttribute('srcset');
            if ($srcset !== '') {
                $urls[] = $this->resolveUrl($baseUrl, $this->pickLargestFromSrcset($srcset));
            }

            foreach (['data-src', 'src'] as $attr) {
                $src = $imgNode->getAttribute($attr);
                if ($src !== '') {
                    $urls[] = $this->resolveUrl($baseUrl, $src);
                }
            }
        }

        foreach ($xpath->query('//source') as $sourceNode) {
            $srcset = $sourceNode->getAttribute('srcset');
            if ($srcset !== '') {
                $urls[] = $this->resolveUrl($baseUrl, $this->pickLargestFromSrcset($srcset));
            }
        }

        $urls = array_values(array_unique(array_filter($urls, fn ($url) => $url !== '')));

        return $urls;
    }

    private function pickLargestFromSrcset(string $srcset): string
    {
        $entries = array_filter(array_map('trim', explode(',', $srcset)));
        $largest = '';
        $largestWidth = 0;

        foreach ($entries as $entry) {
            [$url, $descriptor] = array_pad(preg_split('/\\s+/', $entry, 2), 2, '');
            $width = (int) rtrim($descriptor, 'w');

            if ($width >= $largestWidth) {
                $largestWidth = $width;
                $largest = $url;
            }
        }

        return $largest !== '' ? $largest : ($entries[0] ?? '');
    }

    private function extractBrochureUrl(\DOMXPath $xpath, string $baseUrl): ?string
    {
        foreach ($xpath->query('//a') as $link) {
            $text = strtolower(trim((string) $link->textContent));
            if (str_contains($text, 'brochure')) {
                $href = $link->getAttribute('href');
                if ($href !== '') {
                    return $this->resolveUrl($baseUrl, $href);
                }
            }
        }

        return null;
    }

    private function extractDrawingUrls(\DOMXPath $xpath, string $baseUrl): array
    {
        $urls = [];

        foreach ($xpath->query('//a') as $link) {
            $text = strtolower(trim((string) $link->textContent));
            if (str_contains($text, 'plattegrond') || str_contains($text, 'tekening')) {
                $href = $link->getAttribute('href');
                if ($href !== '') {
                    $urls[] = $this->resolveUrl($baseUrl, $href);
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function resolveUrl(string $baseUrl, string $url): string
    {
        if ($url === '') {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '/';

        if (Str::startsWith($url, '//')) {
            return $scheme.':'.$url;
        }

        if (Str::startsWith($url, '/')) {
            return "{$scheme}://{$host}{$url}";
        }

        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return "{$scheme}://{$host}{$dir}/{$url}";
    }

    private function downloadFile(string $url, string $directory, ?string $externalId, ?int $index = null): ?string
    {
        if ($url === '') {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $extension = $extension !== '' ? $extension : 'pdf';
        $suffix = $index !== null ? "-{$index}" : '';
        $filename = ($externalId ?? Str::uuid()->toString()).$suffix.'.'.$extension;
        $path = "{$directory}/{$filename}";

        $response = $this->http()->get($url);
        if (! $response->successful()) {
            Log::warning('Funda Business asset download failed', ['url' => $url, 'status' => $response->status()]);
            return null;
        }

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function downloadFiles(array $urls, string $directory, ?string $externalId): array
    {
        $paths = [];

        foreach (array_values($urls) as $index => $url) {
            $path = $this->downloadFile($url, $directory, $externalId, $index + 1);
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @return array{0: \DOMDocument, 1: \DOMXPath}
     */
    private function loadDocument(string $html): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return [$document, new \DOMXPath($document)];
    }

    private function getBodyText(\DOMXPath $xpath): string
    {
        $bodyNodes = $xpath->query('//body');
        if ($bodyNodes->length === 0) {
            return '';
        }

        return trim(preg_replace('/\\s+/', ' ', (string) $bodyNodes->item(0)->textContent) ?? '');
    }
}
