<?php

namespace App\Services\Funda;

use Illuminate\Support\Facades\Log;

final class FundaBusinessMapper
{
    /**
     * @return array{payload: array<string, mixed>, notes: string, used_feature_keys: array<int, string>}
     */
    public function map(array $scraped, array $context): array
    {
        $usedKeys = [];
        $flags = [];

        $availabilityFeature = $this->pickFeature($scraped['features'], ['overdracht'], ['status'], $usedKeys);
        $acquisitionFeature = $this->pickFeature($scraped['features'], ['overdracht'], ['aanvaarding'], $usedKeys);
        $surfaceFeature = $this->pickFeature($scraped['features'], ['oppervlakten'], ['oppervlakte'], $usedKeys);
        $parkingFeature = $this->pickFeature($scraped['features'], ['oppervlakten', 'indeling', 'overig', 'parkeren'], ['parkeergelegenheid', 'aantal_parkeerplaatsen', 'parkeerplaatsen'], $usedKeys);
        $rentFeature = $this->pickFeature($scraped['features'], ['overdracht', 'prijzen', 'huur'], ['huurprijs'], $usedKeys, true);
        $askingFeature = $this->pickFeature($scraped['features'], ['overdracht', 'prijzen', 'koop'], ['vraagprijs', 'koopsom'], $usedKeys, true);
        $serviceCostsFeature = $this->pickFeature($scraped['features'], ['overdracht', 'prijzen', 'huur'], ['servicekosten'], $usedKeys, true);
        $parkingRentFeature = $this->pickFeature($scraped['features'], ['overdracht', 'prijzen', 'huur'], ['huurprijs_parkeerplaatsen', 'huurprijs_parkeerplaats', 'parkeerkosten'], $usedKeys, true);
        $cityFeature = $this->pickFeature($scraped['features'], ['adres', 'overig', 'algemeen'], ['plaats', 'stad'], $usedKeys);

        $rentPrice = $this->parsePriceFromValue($rentFeature);
        if ($rentFeature !== null && $this->isOnRequest($rentFeature['value'])) {
            $rentPrice = null;
            $flags['rent_on_request'] = true;
        }

        $rentPricePerM2 = FundaBusinessNormalizer::parseServiceCosts($serviceCostsFeature['value'] ?? null);
        $rentPriceParking = $this->parsePriceFromValue($parkingRentFeature);
        $askingPrice = $this->parsePriceFromValue($askingFeature);

        $surfaceArea = FundaBusinessNormalizer::parseNlInt($surfaceFeature['value'] ?? null);

        $address = $scraped['address_line'] ?? $scraped['title'] ?? null;
        $city = $scraped['city'] ?? $cityFeature['value'] ?? null;

        $normalized = [
            'name' => $scraped['title'] ?? null,
            'address' => $address,
            'city' => $city,
            'surface_area' => $surfaceArea,
            'availability' => $availabilityFeature['value'] ?? null,
            'acquisition' => $acquisitionFeature['value'] ?? null,
            'rent_price' => $rentPrice,
            'rent_price_per_m2' => $rentPricePerM2,
            'rent_price_parking' => $rentPriceParking,
            'asking_price' => $askingPrice,
            'parking_spots' => $parkingFeature['value'] ?? null,
        ];

        $notesPayload = [
            'source' => 'fundainbusiness',
            'external_id' => $scraped['external_id'] ?? null,
            'scraped_at' => $scraped['scraped_at'] ?? null,
            'broker' => $scraped['broker'] ?? [],
            'neighborhood' => $scraped['neighborhood'] ?? null,
            'pricing_display' => $scraped['pricing_display'] ?? null,
            'raw_features' => $scraped['raw_features'] ?? [],
            'normalized' => $normalized,
            'flags' => $flags,
        ];

        $notes = json_encode($notesPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $payload = [
            'organization_id' => $context['organization_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'contact_user_id' => $context['contact_user_id'] ?? null,
            'search_request_id' => $context['search_request_id'] ?? null,
            'name' => $normalized['name'],
            'address' => $address ?? '',
            'city' => $city ?? 'Onbekend',
            'surface_area' => $surfaceArea !== null ? (string) $surfaceArea : '',
            'parking_spots' => $parkingFeature['value'] ?? null,
            'availability' => $availabilityFeature['value'] ?? 'Onbekend',
            'acquisition' => $acquisitionFeature['value'] ?? null,
            'rent_price' => $rentPrice,
            'rent_price_per_m2' => $rentPricePerM2,
            'rent_price_parking' => $rentPriceParking,
            'asking_price' => $askingPrice,
            'images' => $scraped['images'] ?? [],
            'brochure_path' => $scraped['brochure_path'] ?? null,
            'drawings' => $scraped['drawings'] ?? [],
            'notes' => $notes,
            'url' => $scraped['url'] ?? null,
        ];

        $unmappedKeys = array_diff(array_keys($scraped['raw_features'] ?? []), $usedKeys);
        if ($unmappedKeys !== []) {
            Log::info('Funda Business unmapped labels', [
                'url' => $scraped['url'] ?? null,
                'labels' => array_values($unmappedKeys),
            ]);
        }

        return [
            'payload' => $payload,
            'notes' => $notes,
            'used_feature_keys' => $usedKeys,
        ];
    }

    private function pickFeature(array $features, array $sectionSlugs, array $labelSlugs, array &$usedKeys, bool $containsLabel = false): ?array
    {
        foreach ($features as $feature) {
            if (! in_array($feature['section_slug'], $sectionSlugs, true)) {
                continue;
            }

            $label = $feature['label_slug'];
            $matches = $containsLabel
                ? $this->matchesLabelContains($label, $labelSlugs)
                : in_array($label, $labelSlugs, true);

            if (! $matches) {
                continue;
            }

            if (isset($feature['raw_key'])) {
                $usedKeys[] = $feature['raw_key'];
            }

            return $feature;
        }

        return null;
    }

    private function matchesLabelContains(string $label, array $labelSlugs): bool
    {
        foreach ($labelSlugs as $slug) {
            if (str_contains($label, $slug)) {
                return true;
            }
        }

        return false;
    }

    private function parsePriceFromValue(?array $feature): ?float
    {
        if ($feature === null) {
            return null;
        }

        return FundaBusinessNormalizer::parseMoney($feature['value'] ?? null);
    }

    private function isOnRequest(string $value): bool
    {
        return str_contains(strtolower($value), 'op aanvraag');
    }
}
