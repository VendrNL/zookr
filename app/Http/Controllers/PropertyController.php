<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SearchRequest;
use App\Models\User;
use App\Services\Funda\ScrapeFundaBusinessService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    private const ACQUISITIONS = [
        'huur',
        'koop',
    ];

    public function create(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('SearchRequests/OfferProperty', [
            'item' => $search_request->load('organization:id,name'),
            'users' => $users,
            'currentUserId' => $request->user()->id,
            'options' => [
                'acquisitions' => self::ACQUISITIONS,
            ],
        ]);
    }

    public function bagAddressSuggestions(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $response = $this->bagRequest('adressen', [
            'q' => $data['q'],
            'page' => 1,
            'pageSize' => 10,
        ]);

        if (! $response || ! $response->successful()) {
            $fallbackItems = $this->pdokAddressSuggestions($data['q']);
            if (count($fallbackItems) > 0) {
                return response()->json([
                    'items' => $fallbackItems,
                    'message' => 'BAG is niet beschikbaar; suggesties via PDOK Locatieserver.',
                ]);
            }

            return response()->json([
                'items' => [],
                'message' => $this->bagRequestErrorMessage($response, 'Adres suggesties ophalen is mislukt.'),
            ], 422);
        }

        $items = collect(data_get($response->json(), '_embedded.adressen', []))
            ->map(function (array $address) {
                $id = (string) ($address['nummeraanduidingIdentificatie'] ?? '');
                $addressLine = trim((string) ($address['adresregel5'] ?? ''));
                $city = trim((string) ($address['woonplaatsNaam'] ?? ''));
                $postcode = strtoupper((string) ($address['postcode'] ?? ''));

                if (strlen($postcode) === 6) {
                    $postcode = substr($postcode, 0, 4).' '.substr($postcode, 4);
                }

                if ($id === '' || $addressLine === '' || $city === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'address' => $addressLine,
                    'city' => $city,
                    'postcode' => $postcode,
                    'label' => trim($addressLine.', '.trim($postcode.' '.$city)),
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            $fallbackItems = $this->pdokAddressSuggestions($data['q']);
            if (count($fallbackItems) > 0) {
                return response()->json([
                    'items' => $fallbackItems,
                    'message' => 'Geen BAG-resultaten; suggesties via PDOK Locatieserver.',
                ]);
            }
        }

        return response()->json([
            'items' => $items,
        ]);
    }

    public function addressEnrichment(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

        $data = $request->validate([
            'bag_address_id' => ['nullable', 'string', 'max:128', 'required_without:context_key'],
            'context_key' => ['nullable', 'string', 'max:64'],
            'stage' => ['nullable', Rule::in(['all', 'core', 'zoning', 'heritage', 'accessibility', 'milieu'])],
        ]);

        $stage = (string) ($data['stage'] ?? 'all');
        $contextKey = $this->nullableString($data['context_key'] ?? null);
        $isPdokAddress = Str::startsWith((string) ($data['bag_address_id'] ?? ''), 'pdok:');
        $diagnostics = [
            'bag' => [
                'status' => 'ok',
                'detail' => $isPdokAddress
                    ? 'Adres opgehaald via PDOK Locatieserver fallback.'
                    : 'Adres opgehaald uit BAG.',
            ],
            'google_geocode' => ['status' => 'pending', 'detail' => null],
            'geocode_fallback_bag' => ['status' => 'pending', 'detail' => null],
            'pdok_cadastre' => ['status' => 'pending', 'detail' => null],
            'pdok_zoning' => ['status' => 'pending', 'detail' => null],
            'rce_heritage' => ['status' => 'pending', 'detail' => null],
            'osm_poi' => ['status' => 'pending', 'detail' => null],
            'cbs_85830' => ['status' => 'pending', 'detail' => null],
            'rivm_air_quality' => ['status' => 'pending', 'detail' => null],
        ];

        $cachedContext = null;
        if ($contextKey !== null) {
            $cachedContext = Cache::get('address-enrichment:'.$contextKey);
        }

        if (is_array($cachedContext)) {
            $bag = is_array($cachedContext['bag'] ?? null) ? $cachedContext['bag'] : null;
            $geocode = is_array($cachedContext['geocode'] ?? null) ? $cachedContext['geocode'] : null;
            $buildingMarker = is_array($cachedContext['building_marker'] ?? null) ? $cachedContext['building_marker'] : null;
            $bagId = $cachedContext['bag_id'] ?? null;
            $bagViewerUrl = $cachedContext['bag_viewer_url'] ?? null;
            $parcel = is_array($cachedContext['parcel'] ?? null) ? $cachedContext['parcel'] : null;
            $diagnostics = is_array($cachedContext['diagnostics'] ?? null)
                ? array_replace($diagnostics, $cachedContext['diagnostics'])
                : $diagnostics;
        } else {
            $bagAddressId = $this->nullableString($data['bag_address_id'] ?? null);
            if ($bagAddressId === null) {
                return response()->json([
                    'message' => 'Adrescontext ontbreekt. Kies opnieuw een BAG-adres.',
                ], 422);
            }

            $bag = $this->fetchAddressExtendedByIdentifier($bagAddressId);
            if (! $bag) {
                return response()->json([
                    'message' => 'Kon BAG-gegevens niet ophalen voor dit adres.',
                    'diagnostics' => [
                        'bag' => ['status' => 'failed', 'detail' => 'BAG-response bevatte geen bruikbaar adres.'],
                    ],
                ], 422);
            }

            $geocode = $this->googleGeocode($bag['address'].', '.$bag['postcode'].' '.$bag['city']);
            if ($geocode) {
                $diagnostics['google_geocode'] = [
                    'status' => 'ok',
                    'detail' => 'Geocode opgehaald via Google.',
                ];
                $diagnostics['geocode_fallback_bag'] = [
                    'status' => 'skipped',
                    'detail' => 'Niet nodig omdat Google-geocode beschikbaar is.',
                ];
            } else {
                $googleKey = trim((string) config('services.google_maps.api_key'));
                $diagnostics['google_geocode'] = [
                    'status' => $googleKey === '' ? 'missing_key' : 'failed',
                    'detail' => $googleKey === ''
                        ? 'GOOGLE_MAPS_API_KEY ontbreekt.'
                        : 'Google geocode mislukt of geweigerd (bijv. API niet geactiveerd).',
                ];
            }

            if (! $geocode) {
                $geocode = $this->geocodeFromPointWkt($bag['geometry_ll'] ?? null);
                if ($geocode) {
                    $diagnostics['geocode_fallback_bag'] = [
                        'status' => 'ok',
                        'detail' => 'Geocode afgeleid uit PDOK centroid (WGS84).',
                    ];
                }
            }

            if (! $geocode) {
                $geocode = $this->pdokGeocode($bag['address'].', '.$bag['postcode'].' '.$bag['city']);
                if ($geocode) {
                    $diagnostics['geocode_fallback_bag'] = [
                        'status' => 'ok',
                        'detail' => 'Geocode opgehaald via PDOK Locatieserver.',
                    ];
                }
            }

            if (! $geocode) {
                $geocode = $this->geocodeFromBagGeometry($bag['geometry_rd'] ?? null);
                $diagnostics['geocode_fallback_bag'] = [
                    'status' => $geocode ? 'ok' : 'failed',
                    'detail' => $geocode
                        ? 'Geocode afgeleid uit BAG geometrie (RD -> WGS84).'
                        : 'Geen BAG geometrie beschikbaar voor fallback.',
                ];
            }

            $buildingMarker = $this->geocodeFromPointWkt($bag['geometry_ll'] ?? null)
                ?? $this->geocodeFromBagGeometry($bag['geometry_rd'] ?? null)
                ?? $geocode;
            $bag = $this->enrichBagCoreData($bag, $geocode['lat'] ?? null, $geocode['lng'] ?? null);
            $bagId = $bag['adresseerbaar_object_identificatie']
                ?? $bag['nummeraanduiding_identificatie']
                ?? (($bag['pand_identificaties'][0] ?? null) ?: null);
            $bagViewerUrl = $bagId
                ? 'https://bagviewer.kadaster.nl/?objectId='.urlencode((string) $bagId)
                : null;

            $parcel = $this->fetchParcelByPoint(
                $geocode['lat'] ?? null,
                $geocode['lng'] ?? null
            );
            if (! $geocode) {
                $diagnostics['pdok_cadastre'] = [
                    'status' => 'skipped',
                    'detail' => 'Overgeslagen: geen geocode beschikbaar.',
                ];
            } else {
                $diagnostics['pdok_cadastre'] = [
                    'status' => $parcel ? 'ok' : 'no_data',
                    'detail' => $parcel
                        ? 'Kadastrale gegevens gevonden via PDOK.'
                        : 'Geen kadastraal perceel gevonden op het punt (of PDOK gaf geen bruikbare response).',
                ];
            }
        }

        $contextPayload = [
            'bag' => $bag,
            'geocode' => $geocode,
            'building_marker' => $buildingMarker,
            'bag_id' => $bagId,
            'bag_viewer_url' => $bagViewerUrl,
            'parcel' => $parcel,
            'diagnostics' => $diagnostics,
        ];

        if ($contextKey === null) {
            $contextKey = Str::random(32);
        }
        Cache::put('address-enrichment:'.$contextKey, $contextPayload, now()->addMinutes(15));

        $loadZoning = in_array($stage, ['all', 'zoning'], true);
        $loadHeritage = in_array($stage, ['all', 'heritage'], true);
        $loadAccessibility = in_array($stage, ['all', 'accessibility'], true);
        $loadMilieu = in_array($stage, ['all', 'milieu'], true);

        $zoningPlanObjects = [];
        if ($loadZoning) {
            $zoningPlanObjects = $this->fetchZoningPlanObjectsByPoint(
                $geocode['lat'] ?? null,
                $geocode['lng'] ?? null
            );
            if (! $geocode) {
                $diagnostics['pdok_zoning'] = ['status' => 'skipped', 'detail' => 'Overgeslagen: geen geocode beschikbaar.'];
            } else {
                $diagnostics['pdok_zoning'] = [
                    'status' => count($zoningPlanObjects) > 0 ? 'ok' : 'no_data',
                    'detail' => count($zoningPlanObjects) > 0
                        ? 'Planobjecten gevonden via PDOK Ruimtelijke Plannen.'
                        : 'Geen planobject op exact punt (of PDOK gaf geen bruikbare response).',
                ];
            }
        }

        $heritage = null;
        if ($loadHeritage) {
            $heritage = $this->fetchHeritageByPoint(
                $geocode['lat'] ?? null,
                $geocode['lng'] ?? null,
                $bag['address'] ?? null,
                $parcel['kadastrale_aanduiding'] ?? null
            );
            if (! $geocode) {
                $diagnostics['rce_heritage'] = ['status' => 'skipped', 'detail' => 'Overgeslagen: geen geocode beschikbaar.'];
            } else {
                $hasHeritage = count($heritage['rijksmonumenten'] ?? []) > 0
                    || count($heritage['gemeentelijke_monumenten'] ?? []) > 0
                    || count($heritage['gezichten'] ?? []) > 0;
                $diagnostics['rce_heritage'] = [
                    'status' => $hasHeritage ? 'ok' : 'no_data',
                    'detail' => $hasHeritage
                        ? 'Monument/gezicht gevonden via RCE Linked Data.'
                        : 'Geen monument/gezicht op exact punt (of query gaf geen resultaat).',
                ];
            }
        }

        $poiDistances = null;
        $transitDistances = null;
        $buurtCode = null;
        $cbs = null;
        if ($loadAccessibility) {
            $poiLat = is_numeric($buildingMarker['lat'] ?? null) ? (float) $buildingMarker['lat'] : ($geocode['lat'] ?? null);
            $poiLng = is_numeric($buildingMarker['lng'] ?? null) ? (float) $buildingMarker['lng'] : ($geocode['lng'] ?? null);
            $overpassElements = $this->fetchAccessibilityOverpassElements($poiLat, $poiLng);
            $poiDistances = $this->fetchNearestPoiDistances($poiLat, $poiLng, $overpassElements);
            $transitDistances = $this->fetchNearestTransitDistances($poiLat, $poiLng, $overpassElements);
            if (! $geocode) {
                $diagnostics['osm_poi'] = ['status' => 'skipped', 'detail' => 'Overgeslagen: geen geocode beschikbaar.'];
            } else {
                $hasPoiDistance = $this->hasFilledValue($poiDistances['cafe']['afstand_km'] ?? null)
                    || $this->hasFilledValue($poiDistances['restaurant']['afstand_km'] ?? null)
                    || $this->hasFilledValue($poiDistances['hotel']['afstand_km'] ?? null);
                $diagnostics['osm_poi'] = [
                    'status' => $hasPoiDistance ? 'ok' : 'no_data',
                    'detail' => $hasPoiDistance
                        ? 'Afstanden naar dichtstbijzijnde cafe/restaurant/hotel opgehaald via OpenStreetMap Overpass.'
                        : 'Geen cafe/restaurant/hotel gevonden binnen 10 km (of bron niet beschikbaar).',
                ];
            }

            $buurtCode = $this->fetchNeighborhoodCode(
                is_string($bag['adresseerbaar_object_identificatie'] ?? null) ? $bag['adresseerbaar_object_identificatie'] : null,
                is_string($bag['nummeraanduiding_identificatie'] ?? null) ? $bag['nummeraanduiding_identificatie'] : null,
                $bag['address'] ?? null,
                $bag['postcode'] ?? null,
                $bag['city'] ?? null
            );
            $cbs = $this->fetchCbsNeighborhoodStats($buurtCode);
            if ($buurtCode === null) {
                $diagnostics['cbs_85830'] = ['status' => 'skipped', 'detail' => 'Geen buurtcode beschikbaar voor CBS-verrijking.'];
            } else {
                $hasCbsData = $this->hasFilledValue($cbs['afstand_tot_supermarkt_km'] ?? null)
                    || $this->hasFilledValue($cbs['afstand_tot_oprit_hoofdweg_km'] ?? null)
                    || $this->hasFilledValue($cbs['afstand_tot_groen_km'] ?? null);
                $diagnostics['cbs_85830'] = [
                    'status' => $hasCbsData ? 'ok' : 'no_data',
                    'detail' => $hasCbsData
                        ? 'Buurt-/voorzieningscijfers opgehaald via CBS OData (85830NED).'
                        : 'CBS 85830NED gaf geen waarden voor deze buurtcode.',
                ];
            }
        }

        $airQuality = null;
        $soil = null;
        if ($loadMilieu) {
            $airQuality = $this->fetchRivmAirQualityAtPoint(
                $geocode['lat'] ?? null,
                $geocode['lng'] ?? null
            );
            $soil = $this->fetchSoilStatusByPoint(
                $geocode['lat'] ?? null,
                $geocode['lng'] ?? null,
                $bag['geometry_rd'] ?? null
            );
            $hasAirData = $this->hasFilledValue($airQuality['pm25_ug_m3'] ?? null)
                || $this->hasFilledValue($airQuality['no2_ug_m3'] ?? null)
                || $this->hasFilledValue($airQuality['geluid_omgeving']['waarde'] ?? null)
                || $this->hasFilledValue($airQuality['zomerhitte_stad']['waarde'] ?? null)
                || $this->hasFilledValue($airQuality['kans_op_overstroming']['waarde'] ?? null)
                || $this->hasFilledValue($airQuality['gevaarlijke_stoffen_binnen_1km']['waarde'] ?? null);
            $diagnostics['rivm_air_quality'] = [
                'status' => $hasAirData ? 'ok' : 'no_data',
                'detail' => $hasAirData
                    ? 'Luchtkwaliteit (PM2.5/NO2) opgehaald via RIVM WMS.'
                    : 'Geen luchtkwaliteitswaarde ontvangen op dit punt.',
            ];
        }

        $response = [
            'bag_id' => $bagId,
            'context_key' => $contextKey,
            'bag' => [
                'address' => $bag['address'],
                'postcode' => $bag['postcode'],
                'city' => $bag['city'],
                'nummeraanduiding_identificatie' => $bag['nummeraanduiding_identificatie'],
                'adresseerbaar_object_identificatie' => $bag['adresseerbaar_object_identificatie'],
                'pand_identificaties' => $bag['pand_identificaties'],
                'bouwjaar' => $bag['bouwjaar'],
                'gebruiksfunctie' => $bag['gebruiksfunctie'],
                'oppervlakte_m2' => $bag['oppervlakte_m2'],
                'adresseerbaar_object_status' => $bag['adresseerbaar_object_status'],
                'energielabel' => $bag['energielabel'],
                'bag_id' => $bagId,
                'bag_viewer_url' => $bagViewerUrl,
            ],
            'geocode' => $geocode,
            'cadastre' => $parcel,
            'woz' => [
                'beschikbaar_via_open_api' => false,
                'toelichting' => 'WOZ Waardeloket ondersteunt geen algemene open bulk-API voor geautomatiseerde verrijking.',
                'waardeloket_url' => 'https://www.wozwaardeloket.nl/',
            ],
            'diagnostics' => $diagnostics,
            'map' => [
                'google_maps_api_key_available' => (string) config('services.google_maps.api_key') !== '',
                'google_maps_api_key' => (string) config('services.google_maps.api_key'),
                'marker' => $buildingMarker ? [
                    'lat' => $buildingMarker['lat'],
                    'lng' => $buildingMarker['lng'],
                    'source' => $buildingMarker['source'] ?? null,
                ] : null,
                'kadastraal_wms_url' => (string) config('services.pdok.kadastraal_wms_url'),
                'kadastraal_wms_layer' => (string) config('services.pdok.kadastraal_wms_layer'),
                'wegenkaart_grijs_wmts_url' => (string) config('services.pdok.wegenkaart_grijs_wmts_url'),
                'wegenkaart_grijs_wmts_layer' => (string) config('services.pdok.wegenkaart_grijs_wmts_layer'),
                'wegenkaart_grijs_wmts_matrixset' => (string) config('services.pdok.wegenkaart_grijs_wmts_matrixset'),
                'bodemverontreiniging_wms_url' => (string) config('services.pdok.bodemverontreiniging_wms_url'),
                'bodemverontreiniging_wms_layer' => (string) config('services.pdok.bodemverontreiniging_wms_layer'),
                'energielabel_wms_url' => (string) config('services.pdok.energielabel_wms_url'),
                'energielabel_wms_layer' => (string) config('services.pdok.energielabel_wms_layer'),
                'gebruiksfuncties_wms_url' => (string) config('services.pdok.gebruiksfuncties_wms_url', 'https://data.rivm.nl/geo/alo/wms'),
                'gebruiksfuncties_wms_layer' => (string) config('services.pdok.gebruiksfuncties_wms_layer', 'rivm_bag_pandfuncties_actueel,rivm_bag_adresfuncties_actueel'),
                'ruimtelijke_plannen_wms_url' => (string) config('services.pdok.ruimtelijke_plannen_wms_url'),
                'ruimtelijke_plannen_wms_layer' => (string) config('services.pdok.ruimtelijke_plannen_wms_layer'),
                'ruimtelijke_plannen_legend_url' => (string) config('services.pdok.ruimtelijke_plannen_legend_url'),
            ],
        ];

        if ($loadZoning) {
            $response['zoning'] = [
                'wkbp_beschikbaar' => count($zoningPlanObjects) > 0,
                'omgevingsplan_url' => 'https://omgevingswet.overheid.nl/regels-op-de-kaart',
                'planobjecten' => $zoningPlanObjects,
                'toelichting' => count($zoningPlanObjects) > 0
                    ? 'Planobjecten op deze locatie zijn opgehaald via PDOK Ruimtelijke Plannen (WMS GetFeatureInfo).'
                    : 'Geen planobject gevonden op exact punt. Controleer de kaartlink voor omliggende plannen.',
            ];
        }

        if ($loadHeritage && $heritage !== null) {
            $response['heritage'] = $heritage;
        }

        if ($loadAccessibility && is_array($poiDistances) && is_array($transitDistances)) {
            $response['accessibility'] = [
                'ov' => null,
                'auto' => null,
                'afstand_tot_knooppunten' => $cbs['afstand_tot_overstapstation_km'] ?? null,
                'buurtcode' => $buurtCode,
                'mate_van_stedelijkheid' => $cbs['mate_van_stedelijkheid'] ?? null,
                'afstand_tot_supermarkt_km' => $poiDistances['supermarkt']['afstand_km'] ?? ($cbs['afstand_tot_supermarkt_km'] ?? null),
                'sport_en_beweegmogelijkheden' => $cbs['sport_en_beweegmogelijkheden'] ?? null,
                'afstand_tot_sport_km' => $poiDistances['sport']['afstand_km'] ?? null,
                'afstand_tot_treinstation_ov_knooppunt_km' => $transitDistances['station_metro_tram']['afstand_km'] ?? ($cbs['afstand_tot_treinstation_ov_knooppunt_km'] ?? null),
                'afstand_tot_bushalte_km' => $transitDistances['bushalte']['afstand_km'] ?? ($cbs['afstand_tot_bushalte_km'] ?? null),
                'afstand_tot_oprit_hoofdweg_km' => $transitDistances['oprit_hoofdweg']['afstand_km'] ?? ($cbs['afstand_tot_oprit_hoofdweg_km'] ?? null),
                'afstand_tot_groen_km' => $poiDistances['groen']['afstand_km'] ?? ($cbs['afstand_tot_groen_km'] ?? null),
                'afstand_tot_cafe_km' => $poiDistances['cafe']['afstand_km'] ?? null,
                'afstand_tot_restaurant_km' => $poiDistances['restaurant']['afstand_km'] ?? null,
                'afstand_tot_hotel_km' => $poiDistances['hotel']['afstand_km'] ?? null,
                'dichtstbijzijnde' => $poiDistances,
                'dichtstbijzijnde_ov' => $transitDistances,
                'bronnen' => [
                    'https://www.pdok.nl/',
                    'https://www.cbs.nl/',
                    'https://www.openstreetmap.org/',
                    'https://overpass-api.de/',
                ],
            ];
        }

        if ($loadMilieu) {
            $response['air_quality'] = $airQuality;
            $response['soil'] = $soil;
        }

        return response()->json($response);
    }

    public function mapFeatureInfo(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

        $data = $request->validate([
            'mode' => ['required', 'string', Rule::in(['kadaster', 'bodemverontreiniging', 'energielabels', 'gebruiksfuncties', 'bestemmingsplannen'])],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        if ($data['mode'] === 'kadaster') {
            $parcel = $this->fetchParcelByPoint($lat, $lng);
            $notice = null;
            if ($parcel && (($parcel['selection_source'] ?? null) === 'nearest' || ($parcel['near_boundary'] ?? false))) {
                $notice = 'Klikpunt ligt op of zeer dicht bij een perceelgrens. Klik iets verder binnen het perceel voor eenduidige selectie.';
            }

            return response()->json([
                'mode' => 'kadaster',
                'items' => $parcel ? [
                    [
                        'title' => 'Kadastrale aanduiding',
                        'value' => $parcel['kadastrale_aanduiding'] ?? null,
                    ],
                    [
                        'title' => 'Perceelsgrootte (m2)',
                        'value' => $parcel['perceelsgrootte_m2'] ?? null,
                    ],
                    [
                        'title' => 'Identificatie lokaal id',
                        'value' => $parcel['identificatie_lokaal_id'] ?? null,
                    ],
                ] : [],
                'notice' => $notice,
            ]);
        }

        if ($data['mode'] === 'energielabels') {
            $rawItems = $this->fetchWmsFeatureInfoAtPoint(
                (string) config('services.pdok.energielabel_wms_url'),
                (string) config('services.pdok.energielabel_wms_layer'),
                $lat,
                $lng
            );

            $normalized = collect($rawItems)
                ->filter(fn ($item) => is_array($item))
                ->keyBy(function (array $item) {
                    $title = is_string($item['title'] ?? null) ? $item['title'] : '';
                    return Str::lower(str_replace([' ', '_'], '', $title));
                });

            $bagIdentificatie = data_get($normalized->get('identificatie'), 'value');
            $bagViewerUrl = is_string($bagIdentificatie) && trim($bagIdentificatie) !== ''
                ? 'https://bagviewer.kadaster.nl/lvbag/bag-viewer/?searchQuery='.urlencode(trim($bagIdentificatie))
                : null;

            $items = collect([
                [
                    'title' => 'Energielabel',
                    'value' => data_get($normalized->get('dominantlabel'), 'value'),
                ],
                [
                    'title' => 'Hoogste label',
                    'value' => data_get($normalized->get('hoogstelabel'), 'value'),
                ],
                [
                    'title' => 'Laagste label',
                    'value' => data_get($normalized->get('laagstelabel'), 'value'),
                ],
                [
                    'title' => 'Aantal labels',
                    'value' => data_get($normalized->get('aantlabels'), 'value'),
                ],
                [
                    'title' => 'BAG identificatie',
                    'value' => $bagIdentificatie,
                ],
                [
                    'title' => 'Open in BAG Viewer',
                    'value' => $bagViewerUrl ? 'Bekijk BAG object' : null,
                    'url' => $bagViewerUrl,
                ],
                [
                    'title' => 'Bron',
                    'value' => 'RVO Energielabels (WMS)',
                    'url' => 'https://data.rivm.nl/geo/nl/wms?service=WMS&request=GetCapabilities',
                ],
            ])
                ->filter(fn (array $item) => is_string($item['value']) && trim($item['value']) !== '')
                ->values()
                ->all();

            return response()->json([
                'mode' => 'energielabels',
                'items' => $items,
            ]);
        }

        if ($data['mode'] === 'bestemmingsplannen') {
            $rawItems = $this->fetchWmsFeatureInfoAtPoint(
                (string) config('services.pdok.ruimtelijke_plannen_wms_url'),
                (string) config('services.pdok.ruimtelijke_plannen_wms_layer', 'enkelbestemming'),
                $lat,
                $lng
            );

            $normalized = collect($rawItems)
                ->filter(fn ($item) => is_array($item))
                ->keyBy(function (array $item) {
                    $title = is_string($item['title'] ?? null) ? $item['title'] : '';
                    return Str::lower(str_replace([' ', '_', '-'], '', $title));
                });

            $planTekstUrl = data_get($normalized->get('verwijzingnaartekst'), 'value');
            $items = collect([
                [
                    'title' => 'Bestemming',
                    'value' => data_get($normalized->get('bestemmingshoofdgroep'), 'value')
                        ?? data_get($normalized->get('naam'), 'value'),
                ],
                [
                    'title' => 'Plannaam',
                    'value' => data_get($normalized->get('naam'), 'value'),
                ],
                [
                    'title' => 'Type plan',
                    'value' => data_get($normalized->get('typeplan'), 'value'),
                ],
                [
                    'title' => 'Planstatus',
                    'value' => data_get($normalized->get('planstatus'), 'value'),
                ],
                [
                    'title' => 'Dossierstatus',
                    'value' => data_get($normalized->get('dossierstatus'), 'value'),
                ],
                [
                    'title' => 'Overheid',
                    'value' => data_get($normalized->get('naamoverheid'), 'value'),
                ],
                [
                    'title' => 'Naar plantekst',
                    'value' => is_string($planTekstUrl) && trim($planTekstUrl) !== '' ? 'Open plantekst' : null,
                    'url' => is_string($planTekstUrl) && trim($planTekstUrl) !== '' ? trim($planTekstUrl) : null,
                ],
            ])
                ->filter(fn (array $item) => is_string($item['value']) && trim($item['value']) !== '')
                ->values()
                ->all();

            return response()->json([
                'mode' => 'bestemmingsplannen',
                'items' => $items,
            ]);
        }

        if ($data['mode'] === 'gebruiksfuncties') {
            $rawItems = $this->fetchWmsFeatureInfoAtPoint(
                (string) config('services.pdok.gebruiksfuncties_wms_url', 'https://data.rivm.nl/geo/alo/wms'),
                (string) config('services.pdok.gebruiksfuncties_wms_layer', 'rivm_bag_pandfuncties_actueel,rivm_bag_adresfuncties_actueel'),
                $lat,
                $lng
            );

            $items = collect($rawItems)
                ->filter(fn ($item) => is_array($item))
                ->map(function (array $item) {
                    $title = $this->nullableString($item['title'] ?? null);
                    $value = $this->nullableString($item['value'] ?? null);
                    if ($title === null || $value === null) {
                        return null;
                    }

                    return [
                        'title' => $title,
                        'value' => $value,
                    ];
                })
                ->filter()
                ->take(12)
                ->values()
                ->all();

            $items[] = [
                'title' => 'Bron',
                'value' => 'Atlas Leefomgeving BAG gebruiksfuncties',
                'url' => 'https://www.atlasleefomgeving.nl/kaarten?config=3ef897de-127f-471a-959b-93b7597de188',
            ];

            return response()->json([
                'mode' => 'gebruiksfuncties',
                'items' => $items,
            ]);
        }

        $items = $this->fetchWmsFeatureInfoAtPoint(
            (string) config('services.pdok.bodemverontreiniging_wms_url'),
            (string) config('services.pdok.bodemverontreiniging_wms_layer'),
            $lat,
            $lng
        );

        $bodemloketItems = $this->fetchBodemverontreinigingSummary($lat, $lng);
        if (count($bodemloketItems) > 0) {
            $items = $bodemloketItems;
        }

        return response()->json([
            'mode' => 'bodemverontreiniging',
            'items' => $items,
        ]);
    }

    public function store(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $data = $request->validate([
            'address' => ['required', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:120'],
            'bag_address_id' => ['required', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:150'],
            'surface_area' => ['required', 'numeric', 'min:0'],
            'availability' => ['required', 'string', 'max:150'],
            'acquisition' => ['required', 'string', Rule::in(self::ACQUISITIONS)],
            'parking_spots' => ['nullable', 'integer', 'min:0'],
            'rent_price_per_m2' => ['required', 'numeric', 'min:0'],
            'rent_price_parking' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'images' => ['required_without_all:remote_images,cached_images', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'max:5120'],
            'remote_images' => ['nullable', 'array'],
            'remote_images.*' => ['url', 'max:2048'],
            'cached_images' => ['nullable', 'array'],
            'cached_images.*' => ['string'],
            'brochure' => ['nullable', 'file', 'max:10240'],
            'drawings' => ['nullable', 'file', 'max:10240'],
            'contact_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ]);

        $bagAddress = $this->fetchAddressByIdentifier($data['bag_address_id']);
        if (! $bagAddress) {
            return back()
                ->withErrors([
                    'address' => 'Het gekozen adres kon niet uit de BAG worden opgehaald.',
                ])
                ->withInput();
        }

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('properties/images', 'public');
            }
        }

        $remoteImages = $data['remote_images'] ?? [];
        foreach ($remoteImages as $remoteUrl) {
            $downloadedPath = $this->downloadRemoteImage($remoteUrl);
            if ($downloadedPath) {
                $images[] = $downloadedPath;
            }
        }

        $cachedImages = $data['cached_images'] ?? [];
        foreach ($cachedImages as $cachedPath) {
            $movedPath = $this->moveCachedImage($cachedPath);
            if ($movedPath) {
                $images[] = $movedPath;
            }
        }

        $brochurePath = null;
        if ($request->hasFile('brochure')) {
            $brochurePath = $request->file('brochure')->store('properties/brochures', 'public');
        }

        $drawingsPath = null;
        if ($request->hasFile('drawings')) {
            $drawingsPath = $request->file('drawings')->store('properties/drawings', 'public');
        }

        Property::create([
            'organization_id' => $organizationId,
            'user_id' => $request->user()->id,
            'contact_user_id' => $data['contact_user_id'],
            'search_request_id' => $search_request->id,
            'name' => $data['name'],
            'address' => $bagAddress['address'],
            'city' => $bagAddress['city'],
            'surface_area' => (string) $data['surface_area'],
            'parking_spots' => $data['parking_spots'] !== null
                ? (string) $data['parking_spots']
                : null,
            'availability' => $data['availability'],
            'acquisition' => $data['acquisition'],
            'rent_price_per_m2' => $data['rent_price_per_m2'],
            'rent_price_parking' => $data['rent_price_parking'],
            'notes' => $data['notes'],
            'url' => $data['url'],
            'images' => $images,
            'brochure_path' => $brochurePath,
            'drawings' => $drawingsPath ? [$drawingsPath] : null,
        ]);

        return redirect()
            ->route('search-requests.show', [
                'search_request' => $search_request,
                'tab' => 'offers',
            ]);
    }

    public function importFundaBusiness(Request $request, SearchRequest $search_request, ScrapeFundaBusinessService $service)
    {
        $this->authorize('offer', $search_request);

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'contact_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ]);

        $scraped = $service->scrape($data['url'], false);
        $payload = $service->mapScraped($scraped, [
            'organization_id' => $organizationId,
            'user_id' => $request->user()->id,
            'contact_user_id' => $data['contact_user_id'] ?? $request->user()->id,
            'search_request_id' => $search_request->id,
        ]);

        return response()->json([
            'payload' => $payload,
        ]);
    }

    public function cacheRemoteImage(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $cached = $this->downloadRemoteImageToCache($data['url']);
        if (! $cached) {
            return response()->json([
                'message' => 'Afbeelding kon niet worden opgeslagen.',
            ], 422);
        }

        return response()->json($cached);
    }

    public function importFundaBusinessHtml(Request $request, SearchRequest $search_request, ScrapeFundaBusinessService $service)
    {
        $this->authorize('offer', $search_request);

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $data = $request->validate([
            'url' => ['nullable', 'url', 'max:2048'],
            'html' => ['required', 'string'],
            'contact_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ]);

        $scraped = $service->scrapeFromHtml($data['html'], $data['url'] ?? null);
        $payload = $service->mapScraped($scraped, [
            'organization_id' => $organizationId,
            'user_id' => $request->user()->id,
            'contact_user_id' => $data['contact_user_id'] ?? $request->user()->id,
            'search_request_id' => $search_request->id,
        ]);

        return response()->json([
            'payload' => $payload,
        ]);
    }

    public function edit(Request $request, SearchRequest $search_request, Property $property)
    {
        $this->authorize('view', $property);

        if ($property->search_request_id !== $search_request->id) {
            abort(404);
        }

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('SearchRequests/EditProperty', [
            'item' => $search_request->load('organization:id,name'),
            'property' => $property->only([
                'id',
                'address',
                'city',
                'name',
                'surface_area',
                'availability',
                'acquisition',
                'parking_spots',
                'rent_price_per_m2',
                'rent_price_parking',
                'notes',
                'url',
                'contact_user_id',
            ]),
            'propertyMedia' => [
                'images' => collect($property->images ?? [])
                    ->map(fn ($path) => [
                        'path' => $path,
                        'url' => Storage::disk('public')->url($path),
                    ])
                    ->values(),
                'brochure' => $property->brochure_path
                    ? [
                        'path' => $property->brochure_path,
                        'url' => Storage::disk('public')->url($property->brochure_path),
                    ]
                    : null,
                'drawings' => collect($property->drawings ?? [])
                    ->map(fn ($path) => [
                        'path' => $path,
                        'url' => Storage::disk('public')->url($path),
                    ])
                    ->values(),
            ],
            'users' => $users,
            'currentUserId' => $request->user()->id,
            'options' => [
                'acquisitions' => self::ACQUISITIONS,
            ],
        ]);
    }

    public function update(Request $request, SearchRequest $search_request, Property $property)
    {
        $this->authorize('update', $property);

        if ($property->search_request_id !== $search_request->id) {
            abort(404);
        }

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $data = $request->validate([
            'address' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:150'],
            'surface_area' => ['required', 'numeric', 'min:0'],
            'availability' => ['required', 'string', 'max:150'],
            'acquisition' => ['required', 'string', Rule::in(self::ACQUISITIONS)],
            'parking_spots' => ['nullable', 'integer', 'min:0'],
            'rent_price_per_m2' => ['required', 'numeric', 'min:0'],
            'rent_price_parking' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
            'cached_images' => ['nullable', 'array'],
            'cached_images.*' => ['string'],
            'brochure' => ['nullable', 'file', 'max:10240'],
            'drawings' => ['nullable', 'file', 'max:10240'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
            'remove_brochure' => ['nullable', 'boolean'],
            'remove_drawings' => ['nullable', 'array'],
            'remove_drawings.*' => ['string'],
            'contact_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ]);

        $images = $property->images ?? [];
        $removeImages = collect($data['remove_images'] ?? []);
        if ($removeImages->isNotEmpty()) {
            $images = array_values(array_filter(
                $images,
                fn ($path) => ! $removeImages->contains($path)
            ));
            Storage::disk('public')->delete($removeImages->all());
        }
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('properties/images', 'public');
            }
        }

        $cachedImages = $data['cached_images'] ?? [];
        foreach ($cachedImages as $cachedPath) {
            $movedPath = $this->moveCachedImage($cachedPath);
            if ($movedPath) {
                $images[] = $movedPath;
            }
        }

        $brochurePath = $property->brochure_path;
        if ($request->boolean('remove_brochure') && $brochurePath) {
            Storage::disk('public')->delete($brochurePath);
            $brochurePath = null;
        }
        if ($request->hasFile('brochure')) {
            if ($brochurePath) {
                Storage::disk('public')->delete($brochurePath);
            }
            $brochurePath = $request->file('brochure')->store('properties/brochures', 'public');
        }

        $drawings = $property->drawings;
        $removeDrawings = collect($data['remove_drawings'] ?? []);
        if ($removeDrawings->isNotEmpty()) {
            $drawings = array_values(array_filter(
                $drawings ?? [],
                fn ($path) => ! $removeDrawings->contains($path)
            ));
            Storage::disk('public')->delete($removeDrawings->all());
        }
        if ($request->hasFile('drawings')) {
            if ($drawings) {
                Storage::disk('public')->delete($drawings);
            }
            $drawingsPath = $request->file('drawings')->store('properties/drawings', 'public');
            $drawings = [$drawingsPath];
        }

        $property->update([
            'name' => $data['name'],
            'address' => $data['address'],
            'city' => $data['city'],
            'surface_area' => (string) $data['surface_area'],
            'parking_spots' => $data['parking_spots'] !== null
                ? (string) $data['parking_spots']
                : null,
            'availability' => $data['availability'],
            'acquisition' => $data['acquisition'],
            'rent_price_per_m2' => $data['rent_price_per_m2'],
            'rent_price_parking' => $data['rent_price_parking'],
            'notes' => $data['notes'],
            'url' => $data['url'],
            'contact_user_id' => $data['contact_user_id'],
            'images' => $images,
            'brochure_path' => $brochurePath,
            'drawings' => $drawings,
        ]);

        return redirect()->route('search-requests.show', [
            'search_request' => $search_request,
            'tab' => 'offers',
        ]);
    }

    public function view(Request $request, SearchRequest $search_request, Property $property)
    {
        $this->authorize('view', $property);

        if ($property->search_request_id !== $search_request->id) {
            abort(404);
        }

        $property->load([
            'organization:id,name,phone',
            'contactUser:id,name,email,phone,avatar_path',
            'user:id,name,email,phone,avatar_path',
        ]);

        $contactUser = $property->contactUser ?: $property->user;

        return Inertia::render('SearchRequests/ViewProperty', [
            'item' => $search_request->only(['id', 'title', 'organization_id']),
            'property' => $property->only([
                'id',
                'name',
                'address',
                'city',
                'surface_area',
                'parking_spots',
                'availability',
                'acquisition',
                'rent_price_per_m2',
                'rent_price_parking',
                'notes',
                'url',
                'status',
            ]),
            'media' => [
                'images' => collect($property->images ?? [])
                    ->map(fn ($path) => Storage::disk('public')->url($path))
                    ->values(),
                'brochure' => $property->brochure_path
                    ? Storage::disk('public')->url($property->brochure_path)
                    : null,
                'drawings' => collect($property->drawings ?? [])
                    ->map(fn ($path) => Storage::disk('public')->url($path))
                    ->values(),
            ],
            'contact' => [
                'name' => $contactUser?->name,
                'email' => $contactUser?->email,
                'phone' => $contactUser?->phone,
                'avatar_url' => $contactUser?->avatar_url,
                'organization' => [
                    'name' => $property->organization?->name,
                    'phone' => $property->organization?->phone,
                ],
            ],
            'can' => [
                'update' => $request->user()->can('update', $property),
                'setStatus' => $request->user()->can('setStatus', $property),
            ],
        ]);
    }

    public function setStatus(Request $request, SearchRequest $search_request, Property $property)
    {
        $this->authorize('setStatus', $property);

        if ($property->search_request_id !== $search_request->id) {
            abort(404);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['geschikt', 'ongeschikt'])],
        ]);

        $property->update([
            'status' => $data['status'],
        ]);

        return redirect()->back();
    }

    private function downloadRemoteImage(string $url): ?string
    {
        $response = Http::retry(2, 300)->get($url);
        if (! $response->successful()) {
            return null;
        }

        $contentType = $response->header('Content-Type', '');
        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $extension = $this->guessImageExtension($contentType, $url);
        $filename = uniqid('remote_', true).'.'.$extension;
        $path = 'properties/images/'.$filename;

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function downloadRemoteImageToCache(string $url): ?array
    {
        $response = Http::retry(2, 300)->get($url);
        if (! $response->successful()) {
            return null;
        }

        $contentType = $response->header('Content-Type', '');
        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $extension = $this->guessImageExtension($contentType, $url);
        $filename = uniqid('cached_', true).'.'.$extension;
        $path = 'properties/tmp/'.$filename;

        Storage::disk('public')->put($path, $response->body());

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    private function moveCachedImage(string $path): ?string
    {
        if (! Str::startsWith($path, 'properties/tmp/')) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? $extension : 'jpg';
        $newPath = 'properties/images/'.uniqid('cached_', true).'.'.$extension;

        if (Storage::disk('public')->move($path, $newPath)) {
            return $newPath;
        }

        return null;
    }

    private function guessImageExtension(string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (isset($map[$contentType])) {
            return $map[$contentType];
        }

        $path = parse_url($url, PHP_URL_PATH);
        $extension = $path ? pathinfo($path, PATHINFO_EXTENSION) : '';

        return $extension !== '' ? strtolower($extension) : 'jpg';
    }

    private function bagRequest(string $path, array $query = [], array $headers = []): ?Response
    {
        $apiKey = (string) config('services.bag.api_key');
        $baseUrl = rtrim((string) config('services.bag.base_url'), '/');

        if ($apiKey === '' || $baseUrl === '') {
            return null;
        }

        return Http::baseUrl($baseUrl)
            ->withHeaders(array_merge([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/hal+json',
            ], $headers))
            ->timeout(8)
            ->retry(1, 250)
            ->get(ltrim($path, '/'), $query);
    }

    private function fetchBagAddress(string $bagAddressId): ?array
    {
        $response = $this->bagRequest('adressen/'.urlencode($bagAddressId));

        if (! $response || ! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $address = trim((string) data_get($payload, 'adresregel5', ''));
        $city = trim((string) data_get($payload, 'woonplaatsNaam', ''));

        if ($address === '' || $city === '') {
            return null;
        }

        return [
            'address' => $address,
            'city' => $city,
        ];
    }

    private function fetchAddressByIdentifier(string $addressIdentifier): ?array
    {
        if (Str::startsWith($addressIdentifier, 'pdok:')) {
            $pdok = $this->fetchPdokAddressById(Str::after($addressIdentifier, 'pdok:'));
            if (! $pdok) {
                return null;
            }

            return [
                'address' => $pdok['address'],
                'city' => $pdok['city'],
            ];
        }

        return $this->fetchBagAddress($addressIdentifier);
    }

    private function fetchBagAddressExtended(string $bagAddressId): ?array
    {
        $baseResponse = $this->bagRequest('adressen/'.urlencode($bagAddressId));
        if (! $baseResponse || ! $baseResponse->successful()) {
            return null;
        }

        $base = $baseResponse->json();

        $address = trim((string) data_get($base, 'adresregel5', ''));
        $city = trim((string) data_get($base, 'woonplaatsNaam', ''));
        $postcode = strtoupper(trim((string) data_get($base, 'postcode', '')));
        $nummeraanduidingIdentificatie = trim((string) data_get($base, 'nummeraanduidingIdentificatie', $bagAddressId));
        $adresseerbaarObjectIdentificatie = trim((string) data_get($base, 'adresseerbaarObjectIdentificatie', ''));
        $pandIdentificaties = collect(data_get($base, 'pandIdentificaties', []))
            ->filter(fn ($item) => is_string($item) && $item !== '')
            ->values()
            ->all();

        if (strlen($postcode) === 6) {
            $postcode = substr($postcode, 0, 4).' '.substr($postcode, 4);
        }

        if ($address === '' || $city === '') {
            return null;
        }

        $extendedResponse = $this->bagRequest(
            'adressenuitgebreid/'.urlencode($nummeraanduidingIdentificatie),
            [],
            ['Accept-Crs' => 'epsg:28992']
        );
        $extended = ($extendedResponse && $extendedResponse->successful()) ? $extendedResponse->json() : [];
        $details = $this->extractBagExtendedDetails($extended);

        return [
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'nummeraanduiding_identificatie' => $nummeraanduidingIdentificatie,
            'adresseerbaar_object_identificatie' => $adresseerbaarObjectIdentificatie !== '' ? $adresseerbaarObjectIdentificatie : null,
            'pand_identificaties' => count($details['pand_identificaties']) > 0
                ? $details['pand_identificaties']
                : $pandIdentificaties,
            'bouwjaar' => $details['bouwjaar'],
            'gebruiksfunctie' => $details['gebruiksfunctie'],
            'oppervlakte_m2' => $details['oppervlakte_m2'],
            'adresseerbaar_object_status' => $details['adresseerbaar_object_status'],
            'geometry_rd' => $details['geometry_rd'],
            'energielabel' => $details['energielabel'],
        ];
    }

    private function fetchAddressExtendedByIdentifier(string $addressIdentifier): ?array
    {
        if (Str::startsWith($addressIdentifier, 'pdok:')) {
            return $this->fetchPdokAddressExtendedById(Str::after($addressIdentifier, 'pdok:'));
        }

        return $this->fetchBagAddressExtended($addressIdentifier);
    }

    private function googleGeocode(string $address): ?array
    {
        $apiKey = trim((string) config('services.google_maps.api_key'));
        if ($apiKey === '') {
            return null;
        }

        $response = Http::baseUrl('https://maps.googleapis.com/maps/api')
            ->timeout(8)
            ->retry(1, 250)
            ->get('geocode/json', [
                'address' => $address,
                'key' => $apiKey,
                'language' => 'nl',
                'region' => 'nl',
            ]);

        if (! $response->successful() || data_get($response->json(), 'status') !== 'OK') {
            return null;
        }

        $first = data_get($response->json(), 'results.0');
        if (! is_array($first)) {
            return null;
        }

        $lat = data_get($first, 'geometry.location.lat');
        $lng = data_get($first, 'geometry.location.lng');
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'formatted_address' => data_get($first, 'formatted_address'),
            'place_id' => data_get($first, 'place_id'),
            'source' => 'google_geocoding',
        ];
    }

    private function geocodeFromPointWkt(?string $pointWkt): ?array
    {
        if (! $pointWkt) {
            return null;
        }

        if (! preg_match('/POINT\\(([-\\d\\.]+)\\s+([-\\d\\.]+)\\)/i', $pointWkt, $matches)) {
            return null;
        }

        $lng = isset($matches[1]) ? (float) $matches[1] : null;
        $lat = isset($matches[2]) ? (float) $matches[2] : null;
        if (! is_finite($lng) || ! is_finite($lat)) {
            return null;
        }

        return [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'formatted_address' => null,
            'place_id' => null,
            'source' => 'pdok_centroide_ll',
        ];
    }

    private function pdokGeocode(string $address): ?array
    {
        $response = Http::timeout(8)
            ->retry(1, 250)
            ->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free', [
                'q' => $address,
                'rows' => 1,
                'fq' => 'type:adres',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $pointWkt = data_get($response->json(), 'response.docs.0.centroide_ll');
        $parsed = $this->geocodeFromPointWkt(is_string($pointWkt) ? $pointWkt : null);
        if (! $parsed) {
            return null;
        }

        $parsed['source'] = 'pdok_locatieserver';

        return $parsed;
    }

    private function geocodeFromBagGeometry(mixed $coordinates): ?array
    {
        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $x = $coordinates[0] ?? null;
        $y = $coordinates[1] ?? null;
        if (! is_numeric($x) || ! is_numeric($y)) {
            return null;
        }

        $converted = $this->rdToWgs84((float) $x, (float) $y);
        if (! $converted) {
            return null;
        }

        return [
            'lat' => $converted['lat'],
            'lng' => $converted['lng'],
            'formatted_address' => null,
            'place_id' => null,
            'source' => 'bag_geometry_rd',
        ];
    }

    private function rdToWgs84(float $x, float $y): ?array
    {
        // Rijksdriehoek (EPSG:28992) to WGS84 approximation used in Dutch geo tooling.
        $dx = ($x - 155000.0) * 1.0E-5;
        $dy = ($y - 463000.0) * 1.0E-5;

        $lat = 52.1551744
            + (3235.65389 * $dy + -32.58297 * $dx * $dx + -0.2475 * $dy * $dy + -0.84978 * $dx * $dx * $dy + -0.0655 * $dy * $dy * $dy + -0.01709 * $dx * $dx * $dy * $dy + -0.00738 * $dx + 0.0053 * pow($dx, 4) + -0.00039 * $dx * $dx * pow($dy, 3) + 0.00033 * pow($dx, 4) * $dy + -0.00012 * $dx * $dy) / 3600.0;

        $lng = 5.38720621
            + (5260.52916 * $dx + 105.94684 * $dx * $dy + 2.45656 * $dx * $dy * $dy + -0.81885 * pow($dx, 3) + 0.05594 * $dx * pow($dy, 3) + -0.05607 * pow($dx, 3) * $dy + 0.01199 * $dy + -0.00256 * pow($dx, 3) * $dy * $dy + 0.00128 * $dx * pow($dy, 4) + 0.00022 * $dy * $dy + -0.00022 * $dx * $dx + 0.00026 * pow($dx, 5)) / 3600.0;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        return [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
        ];
    }

    private function wgs84ToWebMercator(float $lat, float $lng): ?array
    {
        $originShift = 20037508.342789244;

        $x = ($lng * $originShift) / 180.0;
        $safeLat = max(min($lat, 85.05112878), -85.05112878);
        $y = log(tan((90.0 + $safeLat) * M_PI / 360.0)) / (M_PI / 180.0);
        $y = ($y * $originShift) / 180.0;

        if (! is_finite($x) || ! is_finite($y)) {
            return null;
        }

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    private function buildWmsFeatureInfoPointParams(string $layerNames, float $lat, float $lng, string $infoFormat = 'application/json'): ?array
    {
        $layerNames = trim($layerNames);
        if ($layerNames === '') {
            return null;
        }

        $mercator = $this->wgs84ToWebMercator($lat, $lng);
        if (! $mercator) {
            return null;
        }

        $delta = 60.0;
        $bbox = implode(',', [
            number_format($mercator['x'] - $delta, 3, '.', ''),
            number_format($mercator['y'] - $delta, 3, '.', ''),
            number_format($mercator['x'] + $delta, 3, '.', ''),
            number_format($mercator['y'] + $delta, 3, '.', ''),
        ]);

        return [
            'service' => 'WMS',
            'request' => 'GetFeatureInfo',
            'version' => '1.3.0',
            'crs' => 'EPSG:3857',
            'bbox' => $bbox,
            'width' => 101,
            'height' => 101,
            'i' => 50,
            'j' => 50,
            'layers' => $layerNames,
            'query_layers' => $layerNames,
            'feature_count' => 5,
            'info_format' => $infoFormat,
        ];
    }

    private function fetchWmsFeatureInfoBatchAtPoint(string $wmsUrl, array $layers, float $lat, float $lng): array
    {
        $wmsUrl = trim($wmsUrl);
        if ($wmsUrl === '' || count($layers) === 0) {
            return [];
        }

        $queries = collect($layers)
            ->mapWithKeys(function ($layer, $key) use ($lat, $lng) {
                if (! is_string($layer)) {
                    return [];
                }
                $params = $this->buildWmsFeatureInfoPointParams($layer, $lat, $lng, 'application/json');
                if (! is_array($params)) {
                    return [];
                }

                return [(string) $key => $params];
            })
            ->all();

        if (count($queries) === 0) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($wmsUrl, $queries) {
            $requests = [];
            foreach ($queries as $key => $params) {
                $requests[$key] = $pool
                    ->timeout(8)
                    ->retry(1, 250)
                    ->get($wmsUrl, $params);
            }

            return $requests;
        });

        $result = [];
        foreach ($queries as $key => $_params) {
            $response = $responses[$key] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                $result[$key] = [];
                continue;
            }
            $result[$key] = $this->parseWmsFeatureInfoItems($response);
        }

        return $result;
    }

    private function fetchWmsFeatureInfoAtPoint(string $wmsUrl, string $layerNames, float $lat, float $lng): array
    {
        $wmsUrl = trim($wmsUrl);
        $layerNames = trim($layerNames);

        if ($wmsUrl === '' || $layerNames === '') {
            return [];
        }

        $formatCandidates = [
            'application/json',
            'application/geo+json',
            'application/vnd.esri.wms_featureinfo_xml',
            'application/vnd.esri.wms_raw_xml',
            'text/xml',
            'text/plain',
            'text/html',
        ];

        foreach ($formatCandidates as $infoFormat) {
            $params = $this->buildWmsFeatureInfoPointParams($layerNames, $lat, $lng, $infoFormat);
            if (! is_array($params)) {
                return [];
            }
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get($wmsUrl, $params);

            if (! $response->successful()) {
                continue;
            }

            $items = $this->parseWmsFeatureInfoItems($response);
            if (count($items) > 0) {
                return $items;
            }
        }

        return [];
    }

    private function parseWmsFeatureInfoItems(Response $response): array
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = (string) $response->body();

        if (str_contains($contentType, 'json')) {
            $features = collect(data_get($response->json(), 'features', []))
                ->filter(fn ($feature) => is_array($feature))
                ->values();
            if ($features->isEmpty()) {
                return [];
            }

            $properties = data_get($features->first(), 'properties', []);
            if (! is_array($properties)) {
                return [];
            }

            return $this->mapPropertiesToInfoItems($properties);
        }

        if (str_contains($contentType, 'xml')) {
            return $this->parseFeatureInfoXml($body);
        }

        if (str_contains($contentType, 'text/plain')) {
            return $this->parseFeatureInfoPlainText($body);
        }

        if (str_contains($contentType, 'text/html')) {
            $items = $this->parseFeatureInfoHtml($body);
            if (count($items) > 0) {
                return $items;
            }

            $text = trim(strip_tags($body));
            return $this->parseFeatureInfoPlainText($text);
        }

        return [];
    }

    private function parseFeatureInfoXml(string $xml): array
    {
        if (trim($xml) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $sxml = simplexml_load_string($xml);
        if (! $sxml) {
            return [];
        }

        $result = [];

        // ArcGIS WMS commonly returns <FIELDS .../> nodes with attributes as properties.
        $fieldsNodes = $sxml->xpath('//*[local-name()="FIELDS"]');
        if (is_array($fieldsNodes) && count($fieldsNodes) > 0) {
            $attributes = (array) ($fieldsNodes[0]->attributes() ?? []);
            $first = $attributes['@attributes'] ?? [];
            if (is_array($first) && count($first) > 0) {
                return $this->mapPropertiesToInfoItems($first);
            }
        }

        // Generic fallback: try Key/Value style elements.
        $members = $sxml->xpath('//*[local-name()="FeatureInfo"]/*');
        if (is_array($members)) {
            foreach ($members as $member) {
                $key = trim((string) $member->getName());
                $value = trim((string) $member);
                if ($key !== '' && $value !== '') {
                    $result[$key] = $value;
                }
            }
        }

        return $this->mapPropertiesToInfoItems($result);
    }

    private function parseFeatureInfoPlainText(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $pairs = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'GetFeatureInfo')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
            } elseif (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
            } else {
                continue;
            }

            $keyLower = Str::lower($key);
            if (str_contains($keyLower, 'font') || str_contains($keyLower, 'padding') || str_contains($keyLower, 'border')) {
                continue;
            }

            if ($key !== '' && $value !== '') {
                $pairs[$key] = $value;
            }
        }

        return $this->mapPropertiesToInfoItems($pairs);
    }

    private function parseFeatureInfoHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (! @ $doc->loadHTML($html)) {
            return [];
        }

        $xpath = new \DOMXPath($doc);
        $tables = $xpath->query('//table[contains(@class, "featureInfo")]');
        if (! $tables || $tables->length === 0) {
            return [];
        }

        $firstTable = $tables->item(0);
        if (! $firstTable) {
            return [];
        }

        $headerCells = $xpath->query('.//tr[1]/th', $firstTable);
        $dataRows = $xpath->query('.//tr[position()>1]', $firstTable);
        if (! $headerCells || ! $dataRows || $headerCells->length === 0 || $dataRows->length === 0) {
            return [];
        }

        $headers = [];
        foreach ($headerCells as $cell) {
            $text = trim((string) $cell->textContent);
            $headers[] = $text;
        }

        $row = $dataRows->item(0);
        if (! $row) {
            return [];
        }

        $dataCells = $xpath->query('./td', $row);
        if (! $dataCells || $dataCells->length === 0) {
            return [];
        }

        $pairs = [];
        $count = min($headers ? count($headers) : 0, $dataCells->length);
        for ($i = 0; $i < $count; $i++) {
            $header = trim((string) ($headers[$i] ?? ''));
            $value = trim((string) $dataCells->item($i)->textContent);
            if ($header === '' || $value === '' || Str::lower($header) === 'fid') {
                continue;
            }
            $pairs[$header] = $value;
        }

        return $this->mapPropertiesToInfoItems($pairs);
    }

    private function mapPropertiesToInfoItems(array $properties): array
    {
        return collect($properties)
            ->map(fn ($value, $key) => [
                'title' => Str::headline((string) $key),
                'value' => is_scalar($value) ? trim((string) $value) : null,
            ])
            ->filter(fn (array $item) => $item['value'] !== null && $item['value'] !== '')
            ->take(12)
            ->values()
            ->all();
    }

    private function fetchBodemverontreinigingSummary(float $lat, float $lng): array
    {
        $endpoint = 'https://gis.gdngeoservices.nl/standalone/rest/services/blk_gdn/lks_blk_rd_v1/MapServer/0/query';
        $response = Http::timeout(10)
            ->retry(1, 250)
            ->get($endpoint, [
                'f' => 'json',
                'where' => '1=1',
                'geometry' => $lng.','.$lat,
                'geometryType' => 'esriGeometryPoint',
                'inSR' => 4326,
                'spatialRel' => 'esriSpatialRelIntersects',
                'outFields' => 'WBB_DOSSIER_DBK,TYPE_CD,VERVOLG_WBB,STATUSVER,STATUS_OORD',
                'returnGeometry' => 'false',
                'resultRecordCount' => 1,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $attributes = data_get($response->json(), 'features.0.attributes');
        if (! is_array($attributes)) {
            return [];
        }

        $typeCode = $this->nullableString($attributes['TYPE_CD'] ?? null);
        $typeDescriptionMap = $this->fetchBodemverontreinigingTypeDescriptionMap();
        $typeDescription = $typeCode !== null
            ? ($typeDescriptionMap[$typeCode] ?? 'Onbekend')
            : 'Onbekend';

        $severity = $this->nullableString($attributes['STATUS_OORD'] ?? null) ?? 'Onbekend';
        $followUp = $this->nullableString($attributes['VERVOLG_WBB'] ?? null) ?? 'Onbekend';
        $status = $this->nullableString($attributes['STATUSVER'] ?? null) ?? 'Onbekend';
        $dossier = $this->nullableString($attributes['WBB_DOSSIER_DBK'] ?? null);

        $bodemloketUrl = 'https://www.bodemloket.nl/kaart';
        if ($dossier !== null) {
            $bodemloketUrl .= '?dossier=' . urlencode($dossier);
        }

        return [
            [
                'title' => 'Type verontreiniging',
                'value' => $typeDescription,
            ],
            [
                'title' => 'Ernst',
                'value' => $severity,
            ],
            [
                'title' => 'Vervolgstappen',
                'value' => $followUp,
            ],
            [
                'title' => 'Status',
                'value' => $status,
            ],
            [
                'title' => 'Naar Bodemloket',
                'value' => 'Open rapport/kaart',
                'url' => $bodemloketUrl,
            ],
        ];
    }

    private function fetchSoilStatusByPoint(?float $lat, ?float $lng, mixed $geometryRd): array
    {
        $url = $this->buildBodemloketLocationUrl($geometryRd);

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return [
                'bodemloket_url' => $url,
                'status' => 'Geen gegevens in bodemloket',
            ];
        }

        $endpoint = 'https://gis.gdngeoservices.nl/standalone/rest/services/blk_gdn/lks_blk_rd_v1/MapServer/0/query';
        $response = Http::timeout(10)
            ->retry(1, 250)
            ->get($endpoint, [
                'f' => 'json',
                'where' => '1=1',
                'geometry' => $lng.','.$lat,
                'geometryType' => 'esriGeometryPoint',
                'inSR' => 4326,
                'spatialRel' => 'esriSpatialRelIntersects',
                'outFields' => 'TYPE_CD,STATUS_OORD,STATUSVER',
                'returnGeometry' => 'false',
                'resultRecordCount' => 1,
            ]);

        if (! $response->successful()) {
            return [
                'bodemloket_url' => $url,
                'status' => 'Geen gegevens in bodemloket',
            ];
        }

        $attributes = data_get($response->json(), 'features.0.attributes');
        if (! is_array($attributes)) {
            return [
                'bodemloket_url' => $url,
                'status' => 'Geen gegevens in bodemloket',
            ];
        }

        $typeCode = $this->nullableString($attributes['TYPE_CD'] ?? null);
        $typeDescriptionMap = $this->fetchBodemverontreinigingTypeDescriptionMap();
        $typeDescription = $typeCode !== null
            ? ($typeDescriptionMap[$typeCode] ?? null)
            : null;

        $severity = $this->nullableString($attributes['STATUS_OORD'] ?? null);
        $status = $this->nullableString($attributes['STATUSVER'] ?? null);

        $label = collect([$severity, $status, $typeDescription])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->first();

        return [
            'bodemloket_url' => $url,
            'status' => $label ?? 'Geen gegevens in bodemloket',
        ];
    }

    private function buildBodemloketLocationUrl(mixed $geometryRd): string
    {
        $baseUrl = 'https://www.bodemloket.nl/kaart';
        if (! is_array($geometryRd) || count($geometryRd) < 2) {
            return $baseUrl;
        }

        $x = $geometryRd[0] ?? null;
        $y = $geometryRd[1] ?? null;
        if (! is_numeric($x) || ! is_numeric($y)) {
            return $baseUrl;
        }

        $x = (float) $x;
        $y = (float) $y;

        // Compacte viewport rond het pand in RD-coordinaten (EPSG:28992).
        $minX = (int) round($x - 314);
        $maxX = (int) round($x + 314);
        $minY = (int) round($y - 170);
        $maxY = (int) round($y + 170);

        return sprintf('%s#%d,%d,%d,%d', $baseUrl, $minX, $minY, $maxX, $maxY);
    }

    private function fetchBodemverontreinigingTypeDescriptionMap(): array
    {
        $endpoint = 'https://gis.gdngeoservices.nl/standalone/rest/services/blk_gdn/lks_blk_rd_v1/MapServer/0';
        $response = Http::timeout(10)
            ->retry(1, 250)
            ->get($endpoint, [
                'f' => 'json',
            ]);

        if (! $response->successful()) {
            return [];
        }

        $infos = data_get($response->json(), 'drawingInfo.renderer.uniqueValueInfos', []);
        if (! is_array($infos)) {
            return [];
        }

        return collect($infos)
            ->filter(fn ($row) => is_array($row))
            ->mapWithKeys(function (array $row) {
                $value = $this->nullableString($row['value'] ?? null);
                $label = $this->nullableString($row['label'] ?? null);
                if ($value === null || $label === null) {
                    return [];
                }

                return [$value => $label];
            })
            ->all();
    }

    private function fetchParcelByPoint(?float $lat, ?float $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $delta = 0.00008;
        $bbox = implode(',', [
            number_format($lng - $delta, 6, '.', ''),
            number_format($lat - $delta, 6, '.', ''),
            number_format($lng + $delta, 6, '.', ''),
            number_format($lat + $delta, 6, '.', ''),
        ]);

        $response = Http::timeout(8)
            ->retry(1, 250)
            ->get('https://api.pdok.nl/kadaster/brk-kadastrale-kaart/ogc/v1/collections/perceel/items', [
                'bbox' => $bbox,
                'limit' => 25,
                'f' => 'json',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $features = collect(data_get($response->json(), 'features', []))
            ->filter(fn ($feature) => is_array($feature))
            ->values();

        if ($features->isEmpty()) {
            return null;
        }

        $selectedFeature = $features
            ->first(fn (array $feature) => $this->featureContainsPoint($feature, $lat, $lng));
        $selectionSource = $selectedFeature ? 'contains' : 'nearest';

        if (! $selectedFeature) {
            // Fallback: choose the nearest feature by first ring centroid when geometry check does not match.
            $selectedFeature = $features
                ->sortBy(function (array $feature) use ($lat, $lng) {
                    $centroid = $this->featureApproximateCentroid($feature);
                    if (! $centroid) {
                        return PHP_FLOAT_MAX;
                    }

                    $dLat = $centroid['lat'] - $lat;
                    $dLng = $centroid['lng'] - $lng;

                    return ($dLat * $dLat) + ($dLng * $dLng);
                })
                ->first();
        }

        $properties = is_array(data_get($selectedFeature, 'properties'))
            ? data_get($selectedFeature, 'properties')
            : null;
        if (! is_array($properties)) {
            return null;
        }

        $gemeente = trim((string) ($properties['kadastrale_gemeente_waarde'] ?? ''));
        $sectie = trim((string) ($properties['sectie'] ?? ''));
        $perceelNummer = (string) ($properties['perceelnummer'] ?? '');

        $aanduiding = trim($gemeente.' '.$sectie.' '.$perceelNummer);
        if ($aanduiding === '') {
            $aanduiding = null;
        }

        $nearBoundary = $this->featureIsNearBoundary($selectedFeature, $lat, $lng, 1.0);

        return [
            'kadastrale_aanduiding' => $aanduiding,
            'perceelsgrootte_m2' => isset($properties['kadastrale_grootte_waarde']) && is_numeric($properties['kadastrale_grootte_waarde'])
                ? (int) $properties['kadastrale_grootte_waarde']
                : null,
            'identificatie_lokaal_id' => $properties['identificatie_lokaal_id'] ?? null,
            'selection_source' => $selectionSource,
            'near_boundary' => $nearBoundary,
        ];
    }

    private function featureContainsPoint(array $feature, float $lat, float $lng): bool
    {
        $geometry = data_get($feature, 'geometry');
        if (! is_array($geometry)) {
            return false;
        }

        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;

        if ($type === 'Polygon' && is_array($coordinates)) {
            return $this->polygonContainsPoint($coordinates, $lat, $lng);
        }

        if ($type === 'MultiPolygon' && is_array($coordinates)) {
            foreach ($coordinates as $polygon) {
                if (is_array($polygon) && $this->polygonContainsPoint($polygon, $lat, $lng)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function polygonContainsPoint(array $polygon, float $lat, float $lng): bool
    {
        if (! isset($polygon[0]) || ! is_array($polygon[0])) {
            return false;
        }

        // Point must be inside outer ring and outside any hole rings.
        if (! $this->ringContainsPoint($polygon[0], $lat, $lng)) {
            return false;
        }

        foreach (array_slice($polygon, 1) as $hole) {
            if (is_array($hole) && $this->ringContainsPoint($hole, $lat, $lng)) {
                return false;
            }
        }

        return true;
    }

    private function ringContainsPoint(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $count = count($ring);
        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $pi = $ring[$i] ?? null;
            $pj = $ring[$j] ?? null;
            if (! is_array($pi) || ! is_array($pj)) {
                continue;
            }

            $xi = isset($pi[0]) && is_numeric($pi[0]) ? (float) $pi[0] : null;
            $yi = isset($pi[1]) && is_numeric($pi[1]) ? (float) $pi[1] : null;
            $xj = isset($pj[0]) && is_numeric($pj[0]) ? (float) $pj[0] : null;
            $yj = isset($pj[1]) && is_numeric($pj[1]) ? (float) $pj[1] : null;

            if ($xi === null || $yi === null || $xj === null || $yj === null) {
                continue;
            }

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi)) / (($yj - $yi) ?: 1.0E-12) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function featureApproximateCentroid(array $feature): ?array
    {
        $geometry = data_get($feature, 'geometry');
        if (! is_array($geometry)) {
            return null;
        }

        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates)) {
            return null;
        }

        $ring = null;
        if ($type === 'Polygon') {
            $ring = $coordinates[0] ?? null;
        } elseif ($type === 'MultiPolygon') {
            $ring = $coordinates[0][0] ?? null;
        }

        if (! is_array($ring) || count($ring) === 0) {
            return null;
        }

        $sumLng = 0.0;
        $sumLat = 0.0;
        $n = 0;
        foreach ($ring as $point) {
            if (! is_array($point)) {
                continue;
            }

            $lng = isset($point[0]) && is_numeric($point[0]) ? (float) $point[0] : null;
            $lat = isset($point[1]) && is_numeric($point[1]) ? (float) $point[1] : null;
            if ($lng === null || $lat === null) {
                continue;
            }

            $sumLng += $lng;
            $sumLat += $lat;
            $n++;
        }

        if ($n === 0) {
            return null;
        }

        return [
            'lat' => $sumLat / $n,
            'lng' => $sumLng / $n,
        ];
    }

    private function featureIsNearBoundary(array $feature, float $lat, float $lng, float $thresholdMeters): bool
    {
        $geometry = data_get($feature, 'geometry');
        if (! is_array($geometry)) {
            return false;
        }

        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates)) {
            return false;
        }

        $minDistance = null;

        if ($type === 'Polygon') {
            $minDistance = $this->polygonMinDistanceToBoundaryMeters($coordinates, $lat, $lng);
        } elseif ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if (! is_array($polygon)) {
                    continue;
                }

                $distance = $this->polygonMinDistanceToBoundaryMeters($polygon, $lat, $lng);
                if ($distance === null) {
                    continue;
                }

                $minDistance = $minDistance === null ? $distance : min($minDistance, $distance);
            }
        }

        return $minDistance !== null && $minDistance <= $thresholdMeters;
    }

    private function polygonMinDistanceToBoundaryMeters(array $polygon, float $lat, float $lng): ?float
    {
        $minDistance = null;

        foreach ($polygon as $ring) {
            if (! is_array($ring)) {
                continue;
            }

            $distance = $this->ringMinDistanceMeters($ring, $lat, $lng);
            if ($distance === null) {
                continue;
            }

            $minDistance = $minDistance === null ? $distance : min($minDistance, $distance);
        }

        return $minDistance;
    }

    private function ringMinDistanceMeters(array $ring, float $lat, float $lng): ?float
    {
        $count = count($ring);
        if ($count < 2) {
            return null;
        }

        $minDistance = null;
        for ($i = 0; $i < $count - 1; $i++) {
            $a = $ring[$i] ?? null;
            $b = $ring[$i + 1] ?? null;
            if (! is_array($a) || ! is_array($b)) {
                continue;
            }

            $ax = isset($a[0]) && is_numeric($a[0]) ? (float) $a[0] : null;
            $ay = isset($a[1]) && is_numeric($a[1]) ? (float) $a[1] : null;
            $bx = isset($b[0]) && is_numeric($b[0]) ? (float) $b[0] : null;
            $by = isset($b[1]) && is_numeric($b[1]) ? (float) $b[1] : null;
            if ($ax === null || $ay === null || $bx === null || $by === null) {
                continue;
            }

            $distance = $this->pointToSegmentDistanceMeters($lng, $lat, $ax, $ay, $bx, $by);
            $minDistance = $minDistance === null ? $distance : min($minDistance, $distance);
        }

        return $minDistance;
    }

    private function pointToSegmentDistanceMeters(
        float $px,
        float $py,
        float $x1,
        float $y1,
        float $x2,
        float $y2
    ): float {
        // Equirectangular approximation around local latitude for meter precision at parcel scale.
        $latRad = deg2rad($py);
        $mx = 111320.0 * cos($latRad);
        $my = 110540.0;

        $pxm = $px * $mx;
        $pym = $py * $my;
        $x1m = $x1 * $mx;
        $y1m = $y1 * $my;
        $x2m = $x2 * $mx;
        $y2m = $y2 * $my;

        $dx = $x2m - $x1m;
        $dy = $y2m - $y1m;
        $len2 = ($dx * $dx) + ($dy * $dy);
        if ($len2 <= 0.0) {
            return sqrt((($pxm - $x1m) ** 2) + (($pym - $y1m) ** 2));
        }

        $t = (($pxm - $x1m) * $dx + ($pym - $y1m) * $dy) / $len2;
        $t = max(0.0, min(1.0, $t));
        $projX = $x1m + ($t * $dx);
        $projY = $y1m + ($t * $dy);

        return sqrt((($pxm - $projX) ** 2) + (($pym - $projY) ** 2));
    }

    private function fetchZoningPlanObjectsByPoint(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return [];
        }

        $wmsUrl = trim((string) config('services.pdok.ruimtelijke_plannen_wms_url'));
        if ($wmsUrl === '') {
            return [];
        }

        $delta = 0.001;
        $minLng = $lng - $delta;
        $minLat = $lat - $delta;
        $maxLng = $lng + $delta;
        $maxLat = $lat + $delta;
        $layers = 'plangebied,bestemmingsplangebied';

        $response = Http::timeout(10)
            ->retry(1, 250)
            ->get($wmsUrl, [
                'service' => 'WMS',
                'request' => 'GetFeatureInfo',
                'version' => '1.3.0',
                'crs' => 'CRS:84',
                'bbox' => implode(',', [
                    number_format($minLng, 6, '.', ''),
                    number_format($minLat, 6, '.', ''),
                    number_format($maxLng, 6, '.', ''),
                    number_format($maxLat, 6, '.', ''),
                ]),
                'width' => 256,
                'height' => 256,
                'i' => 128,
                'j' => 128,
                'layers' => $layers,
                'query_layers' => $layers,
                'info_format' => 'application/json',
                'feature_count' => 10,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $features = collect(data_get($response->json(), 'features', []))
            ->filter(fn ($feature) => is_array($feature))
            ->map(function (array $feature) {
                $properties = is_array($feature['properties'] ?? null)
                    ? $feature['properties']
                    : [];

                $identificatie = trim((string) ($properties['identificatie'] ?? ''));

                return [
                    'identificatie' => $identificatie !== '' ? $identificatie : null,
                    'naam' => ($properties['naam'] ?? null) ?: null,
                    'typeplan' => ($properties['typeplan'] ?? null) ?: null,
                    'planstatus' => ($properties['planstatus'] ?? null) ?: null,
                    'dossierstatus' => ($properties['dossierstatus'] ?? null) ?: null,
                    'naamoverheid' => ($properties['naamoverheid'] ?? null) ?: null,
                    'verwijzing_tekst_urls' => $this->extractUrlList($properties['verwijzingnaartekst'] ?? null),
                    'verwijzing_vaststellingsbesluit_urls' => $this->extractUrlList($properties['verwijzingnaarvaststellingsbesluit'] ?? null),
                    'bron_plangebied' => ($properties['plangebied'] ?? null) ?: null,
                ];
            })
            ->filter(fn (array $item) => $item['identificatie'] !== null || $item['naam'] !== null)
            ->unique(fn (array $item) => ($item['identificatie'] ?? '').'|'.($item['naam'] ?? ''))
            ->values()
            ->all();

        return $features;
    }

    private function fetchAccessibilityOverpassUrls(): array
    {
        return collect([
            trim((string) config('services.overpass.url')),
            'https://overpass-api.de/api/interpreter',
        ])
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function fetchAccessibilityOverpassElements(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return [];
        }

        $overpassUrls = $this->fetchAccessibilityOverpassUrls();
        if (count($overpassUrls) === 0) {
            return [];
        }

        $latStr = number_format($lat, 7, '.', '');
        $lngStr = number_format($lng, 7, '.', '');
        $query = <<<OVERPASS
[out:json][timeout:20];
(
  nwr["amenity"="supermarket"](around:10000,{$latStr},{$lngStr});
  nwr["leisure"~"sports_centre|sports_hall|fitness_centre|pitch|stadium"](around:10000,{$latStr},{$lngStr});
  nwr["leisure"="park"](around:10000,{$latStr},{$lngStr});
  nwr["natural"~"wood|grassland|heath"](around:10000,{$latStr},{$lngStr});
  nwr["landuse"~"forest|grass"](around:10000,{$latStr},{$lngStr});
  nwr["amenity"="cafe"](around:10000,{$latStr},{$lngStr});
  nwr["amenity"="restaurant"](around:10000,{$latStr},{$lngStr});
  nwr["tourism"="hotel"](around:10000,{$latStr},{$lngStr});
  nwr["highway"="bus_stop"](around:10000,{$latStr},{$lngStr});
  nwr["railway"="station"](around:10000,{$latStr},{$lngStr});
  nwr["railway"="tram_stop"](around:10000,{$latStr},{$lngStr});
  nwr["station"="subway"](around:10000,{$latStr},{$lngStr});
  nwr["highway"="motorway_junction"](around:10000,{$latStr},{$lngStr});
  nwr["highway"="motorway_link"](around:10000,{$latStr},{$lngStr});
);
out center tags;
OVERPASS;

        return $this->fetchOverpassElementsWithQuery($query, true);
    }

    private function fetchOverpassElementsWithQuery(string $query, bool $allowPlainFallback = false): array
    {
        $overpassUrls = $this->fetchAccessibilityOverpassUrls();
        if (count($overpassUrls) === 0) {
            return [];
        }

        foreach ($overpassUrls as $overpassUrl) {
            try {
                $response = Http::timeout(12)
                    ->retry(1, 450)
                    ->asForm()
                    ->post($overpassUrl, ['data' => $query]);

                if ($allowPlainFallback && ! $response->successful()) {
                    $response = Http::timeout(12)
                        ->retry(1, 450)
                        ->withHeaders([
                            'Content-Type' => 'text/plain;charset=UTF-8',
                            'User-Agent' => 'zookr/1.0',
                        ])
                        ->post($overpassUrl, $query);
                }
            } catch (\Throwable) {
                continue;
            }

            if (! isset($response) || ! $response->successful()) {
                continue;
            }

            $elements = data_get($response->json(), 'elements', []);
            if (is_array($elements) && count($elements) > 0) {
                return $elements;
            }
        }

        return [];
    }

    private function buildPoiOverpassQuery(float $lat, float $lng): string
    {
        $latStr = number_format($lat, 7, '.', '');
        $lngStr = number_format($lng, 7, '.', '');

        return <<<OVERPASS
[out:json][timeout:20];
(
  nwr["amenity"="supermarket"](around:10000,{$latStr},{$lngStr});
  nwr["leisure"~"sports_centre|sports_hall|fitness_centre|pitch|stadium"](around:10000,{$latStr},{$lngStr});
  nwr["leisure"="park"](around:10000,{$latStr},{$lngStr});
  nwr["natural"~"wood|grassland|heath"](around:10000,{$latStr},{$lngStr});
  nwr["landuse"~"forest|grass"](around:10000,{$latStr},{$lngStr});
  nwr["amenity"="cafe"](around:10000,{$latStr},{$lngStr});
  nwr["amenity"="restaurant"](around:10000,{$latStr},{$lngStr});
  nwr["tourism"="hotel"](around:10000,{$latStr},{$lngStr});
);
out center tags;
OVERPASS;
    }

    private function buildTransitOverpassQuery(float $lat, float $lng): string
    {
        $latStr = number_format($lat, 7, '.', '');
        $lngStr = number_format($lng, 7, '.', '');

        return <<<OVERPASS
[out:json][timeout:20];
(
  nwr["highway"="bus_stop"](around:10000,{$latStr},{$lngStr});
  nwr["railway"="station"](around:10000,{$latStr},{$lngStr});
  nwr["railway"="tram_stop"](around:10000,{$latStr},{$lngStr});
  nwr["station"="subway"](around:10000,{$latStr},{$lngStr});
  nwr["highway"="motorway_junction"](around:10000,{$latStr},{$lngStr});
  nwr["highway"="motorway_link"](around:10000,{$latStr},{$lngStr});
);
out center tags;
OVERPASS;
    }

    private function fetchNearestPoiDistances(?float $lat, ?float $lng, ?array $preloadedElements = null): array
    {
        $result = [
            'supermarkt' => ['afstand_km' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'sport' => ['afstand_km' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'groen' => ['afstand_km' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'cafe' => ['afstand_km' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'restaurant' => ['afstand_km' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'hotel' => ['afstand_km' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
        ];

        if ($lat === null || $lng === null) {
            return $result;
        }

        $elements = is_array($preloadedElements) && count($preloadedElements) > 0
            ? $preloadedElements
            : $this->fetchOverpassElementsWithQuery($this->buildPoiOverpassQuery($lat, $lng), true);
        if (! is_array($elements)) {
            return $result;
        }

        $nearest = [
            'supermarkt' => null,
            'sport' => null,
            'groen' => null,
            'cafe' => null,
            'restaurant' => null,
            'hotel' => null,
        ];

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $category = $this->detectPoiCategory($element);
            if (! $category) {
                continue;
            }

            $poiLat = data_get($element, 'lat');
            $poiLng = data_get($element, 'lon');
            if (! is_numeric($poiLat) || ! is_numeric($poiLng)) {
                $poiLat = data_get($element, 'center.lat');
                $poiLng = data_get($element, 'center.lon');
            }
            if (! is_numeric($poiLat) || ! is_numeric($poiLng)) {
                continue;
            }

            $distanceMeters = $this->haversineDistanceMeters($lat, $lng, (float) $poiLat, (float) $poiLng);
            if (! is_finite($distanceMeters)) {
                continue;
            }

            if (! isset($nearest[$category]) || $nearest[$category] === null || $distanceMeters < ($nearest[$category]['distance_m'] ?? INF)) {
                $nearest[$category] = [
                    'distance_m' => $distanceMeters,
                    'name' => $this->nullableString(data_get($element, 'tags.name')),
                    'type' => (string) data_get($element, 'type', ''),
                    'id' => data_get($element, 'id'),
                    'lat' => (float) $poiLat,
                    'lng' => (float) $poiLng,
                ];
            }
        }

        foreach (['supermarkt', 'sport', 'groen', 'cafe', 'restaurant', 'hotel'] as $category) {
            $item = $nearest[$category];
            if (! is_array($item) || ! is_numeric($item['distance_m'] ?? null)) {
                continue;
            }

            $result[$category] = [
                'afstand_km' => $this->roundDistanceKm((float) $item['distance_m']),
                'naam' => $item['name'],
                'osm_url' => $this->buildOsmObjectUrl(
                    is_string($item['type'] ?? null) ? $item['type'] : null,
                    $item['id'] ?? null
                ),
                'lat' => is_numeric($item['lat'] ?? null) ? (float) $item['lat'] : null,
                'lng' => is_numeric($item['lng'] ?? null) ? (float) $item['lng'] : null,
            ];
        }

        $result = $this->fillMissingPoiDistancesFromGooglePlaces($lat, $lng, $result);

        return $result;
    }

    private function fillMissingPoiDistancesFromGooglePlaces(float $lat, float $lng, array $result): array
    {
        $apiKey = trim((string) config('services.google_maps.api_key'));
        if ($apiKey === '') {
            return $result;
        }

        $categoriesToFill = collect(['sport', 'groen', 'cafe', 'restaurant', 'hotel'])
            ->filter(fn (string $category) => ! is_numeric(data_get($result, "{$category}.afstand_km")))
            ->values()
            ->all();

        foreach ($categoriesToFill as $category) {
            $candidate = $this->fetchNearestGooglePlaceCandidate($lat, $lng, $category, $apiKey);
            if (! is_array($candidate)) {
                continue;
            }

            $result[$category] = [
                'afstand_km' => $this->roundDistanceKm((float) $candidate['distance_m']),
                'naam' => $this->nullableString($candidate['name'] ?? null),
                'osm_url' => null,
                'lat' => is_numeric($candidate['lat'] ?? null) ? (float) $candidate['lat'] : null,
                'lng' => is_numeric($candidate['lng'] ?? null) ? (float) $candidate['lng'] : null,
            ];
        }

        return $result;
    }

    private function fetchNearestGooglePlaceCandidate(float $lat, float $lng, string $category, string $apiKey): ?array
    {
        $querySets = match ($category) {
            'sport' => [
                ['type' => 'gym'],
                ['keyword' => 'sport'],
            ],
            'groen' => [
                ['type' => 'park'],
                ['keyword' => 'groen'],
            ],
            'cafe' => [
                ['type' => 'cafe'],
            ],
            'restaurant' => [
                ['type' => 'restaurant'],
            ],
            'hotel' => [
                ['type' => 'lodging', 'keyword' => 'hotel'],
                ['keyword' => 'hotel'],
                ['type' => 'lodging'],
            ],
            default => [],
        };

        foreach ($querySets as $querySet) {
            $candidate = $this->fetchNearestGooglePlaceByQuerySet($lat, $lng, $category, $apiKey, $querySet);
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function fetchNearestGooglePlaceByQuerySet(float $lat, float $lng, string $category, string $apiKey, array $querySet): ?array
    {
        $params = array_filter([
            'location' => number_format($lat, 7, '.', '').','.number_format($lng, 7, '.', ''),
            'rankby' => 'distance',
            'type' => $this->nullableString($querySet['type'] ?? null),
            'keyword' => $this->nullableString($querySet['keyword'] ?? null),
            'key' => $apiKey,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $params);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $status = strtoupper((string) data_get($response->json(), 'status', ''));
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            return null;
        }
        if ($status === 'ZERO_RESULTS') {
            return null;
        }

        $candidate = collect(data_get($response->json(), 'results', []))
            ->filter(fn ($row) => is_array($row))
            ->filter(fn (array $row) => $this->isGooglePlaceMatchForCategory($category, $row))
            ->map(function (array $row) use ($lat, $lng) {
                $candidateLat = data_get($row, 'geometry.location.lat');
                $candidateLng = data_get($row, 'geometry.location.lng');
                if (! is_numeric($candidateLat) || ! is_numeric($candidateLng)) {
                    return null;
                }

                $distanceMeters = $this->haversineDistanceMeters($lat, $lng, (float) $candidateLat, (float) $candidateLng);
                if (! is_finite($distanceMeters)) {
                    return null;
                }

                return [
                    'distance_m' => $distanceMeters,
                    'name' => $this->nullableString($row['name'] ?? null),
                    'lat' => (float) $candidateLat,
                    'lng' => (float) $candidateLng,
                ];
            })
            ->filter(fn ($row) => is_array($row))
            ->sortBy('distance_m')
            ->first();

        return is_array($candidate) ? $candidate : null;
    }

    private function isGooglePlaceMatchForCategory(string $category, array $place): bool
    {
        $types = collect($place['types'] ?? [])
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => strtolower($value))
            ->values()
            ->all();
        $name = strtolower((string) ($place['name'] ?? ''));

        if ($category === 'hotel') {
            $looksLikeHotel = in_array('lodging', $types, true) || str_contains($name, 'hotel');
            $excluded = str_contains($name, 'hostel')
                || str_contains($name, 'camping')
                || str_contains($name, 'campground')
                || str_contains($name, 'vakantiepark')
                || str_contains($name, 'bed and breakfast')
                || str_contains($name, 'b&b');

            return $looksLikeHotel && ! $excluded;
        }

        if ($category === 'restaurant') {
            $looksLikeRestaurant = in_array('restaurant', $types, true);
            $excludedType = in_array('meal_takeaway', $types, true)
                || in_array('meal_delivery', $types, true)
                || in_array('fast_food', $types, true);
            $excludedName = str_contains($name, 'snackbar')
                || str_contains($name, 'snack bar')
                || str_contains($name, 'shoarma')
                || str_contains($name, 'doner')
                || str_contains($name, 'kebab')
                || str_contains($name, 'friet')
                || str_contains($name, 'pizza to go')
                || str_contains($name, 'afhaal');

            return $looksLikeRestaurant && ! $excludedType && ! $excludedName;
        }

        return true;
    }

    private function fetchNeighborhoodCode(
        ?string $adresseerbaarObjectId,
        ?string $nummeraanduidingId,
        mixed $address,
        mixed $postcode,
        mixed $city
    ): ?string {
        $findFromPdok = function (array $params): ?string {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free', $params);
            if (! $response->successful()) {
                return null;
            }

            return $this->nullableString(data_get($response->json(), 'response.docs.0.buurtcode'));
        };

        if (is_string($adresseerbaarObjectId) && trim($adresseerbaarObjectId) !== '') {
            $code = $findFromPdok([
                'q' => '*:*',
                'fq' => '(adresseerbaarobject_id:'.trim($adresseerbaarObjectId).')',
                'rows' => 1,
            ]);
            if ($code) {
                return $code;
            }
        }

        if (is_string($nummeraanduidingId) && trim($nummeraanduidingId) !== '') {
            $code = $findFromPdok([
                'q' => '*:*',
                'fq' => '(nummeraanduiding_id:'.trim($nummeraanduidingId).')',
                'rows' => 1,
            ]);
            if ($code) {
                return $code;
            }
        }

        $query = trim((string) $address.' '.(string) $postcode.' '.(string) $city);
        if ($query !== '') {
            return $findFromPdok([
                'q' => $query,
                'rows' => 1,
            ]);
        }

        return null;
    }

    private function fetchCbsNeighborhoodStats(?string $buurtCode): array
    {
        $result = [
            'afstand_tot_supermarkt_km' => null,
            'sport_en_beweegmogelijkheden' => null,
            'afstand_tot_treinstation_ov_knooppunt_km' => null,
            'afstand_tot_overstapstation_km' => null,
            'afstand_tot_bushalte_km' => null,
            'afstand_tot_oprit_hoofdweg_km' => null,
            'afstand_tot_groen_km' => null,
            'mate_van_stedelijkheid' => null,
            'bron' => 'CBS 85830NED',
        ];

        if (! is_string($buurtCode) || trim($buurtCode) === '') {
            return $result;
        }

        $baseUrl = rtrim((string) config('services.cbs.odata_base_url'), '/');
        $table = trim((string) config('services.cbs.neighborhood_table', '85830NED'));
        if ($baseUrl === '' || $table === '') {
            return $result;
        }

        $url = sprintf('%s/%s/Observations', $baseUrl, $table);
        $response = Http::timeout(12)
            ->retry(1, 300)
            ->get($url, [
                '$filter' => "WijkenEnBuurten eq '".trim($buurtCode)."'",
            ]);

        if (! $response->successful()) {
            return $result;
        }

        $rows = collect(data_get($response->json(), 'value', []))
            ->filter(fn ($row) => is_array($row))
            ->keyBy(fn (array $row) => (string) ($row['Measure'] ?? ''));

        $valueFor = function (string $measureCode) use ($rows): ?float {
            $row = $rows->get($measureCode);
            if (! is_array($row)) {
                return null;
            }
            $value = $row['Value'] ?? null;
            return is_numeric($value) ? (float) $value : null;
        };
        $valueForAny = function (array $measureCodes) use ($valueFor): ?float {
            foreach ($measureCodes as $code) {
                $value = $valueFor((string) $code);
                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        };

        $distanceSupermarket = $valueFor('D000025');
        $distanceSport = $valueFor('D000051');
        $distanceTrain = $valueFor('D000052');
        $distanceHub = $valueFor('D000014');
        $distanceBus = $valueForAny(['D000053', 'D000015', 'D000054']);
        $distanceHighwayRamp = $valueFor('D000037');
        $distanceGreen = $valueFor('D000036');

        $result['afstand_tot_supermarkt_km'] = $distanceSupermarket;
        $result['afstand_tot_treinstation_ov_knooppunt_km'] = $distanceTrain;
        $result['afstand_tot_overstapstation_km'] = $distanceHub;
        $result['afstand_tot_bushalte_km'] = $distanceBus;
        $result['afstand_tot_oprit_hoofdweg_km'] = $distanceHighwayRamp;
        $result['afstand_tot_groen_km'] = $distanceGreen;

        if ($distanceSport !== null) {
            $result['sport_en_beweegmogelijkheden'] = $distanceSport <= 1.0
                ? 'Goed'
                : ($distanceSport <= 3.0 ? 'Redelijk' : 'Beperkt');
        }

        return $result;
    }

    private function fetchRivmAirQualityAtPoint(?float $lat, ?float $lng): array
    {
        $result = [
            'pm25_ug_m3' => null,
            'no2_ug_m3' => null,
            'jaar' => '2023',
            'geluid_omgeving' => [
                'waarde' => null,
                'score' => null,
            ],
            'zomerhitte_stad' => [
                'waarde' => null,
                'score' => null,
            ],
            'kans_op_overstroming' => [
                'waarde' => null,
                'score' => null,
            ],
            'gevaarlijke_stoffen_binnen_1km' => [
                'waarde' => null,
                'score' => null,
            ],
            'bronnen' => [
                'https://data.overheid.nl/dataset/68115-fijnstof-pm2-5--2023-',
                'https://data.overheid.nl/dataset/68112-stikstofdioxide---no----2023-',
                'https://www.atlasleefomgeving.nl/',
            ],
        ];

        if ($lat === null || $lng === null) {
            return $result;
        }

        $wmsUrl = trim((string) config('services.rivm.air_wms_url'));
        $pmLayer = trim((string) config('services.rivm.air_pm25_layer'));
        $no2Layer = trim((string) config('services.rivm.air_no2_layer'));
        if ($wmsUrl === '' || $pmLayer === '' || $no2Layer === '') {
            return $result;
        }

        $geluidLayer = trim((string) config('services.rivm.alo_geluid_layer'));
        $zomerhitteLayer = trim((string) config('services.rivm.alo_zomerhitte_layer'));
        $overstromingLayer = trim((string) config('services.rivm.alo_overstroming_layer'));
        $gevaarLayer = trim((string) config('services.rivm.alo_gevaarlijke_stoffen_layer'));

        $layerMap = [
            'pm25' => $pmLayer,
            'no2' => $no2Layer,
            'geluid' => $geluidLayer,
            'zomerhitte' => $zomerhitteLayer,
            'overstroming' => $overstromingLayer,
            'gevaar' => $gevaarLayer,
        ];
        $batchItems = $this->fetchWmsFeatureInfoBatchAtPoint($wmsUrl, $layerMap, $lat, $lng);

        $itemsFor = function (string $key, string $layer) use ($batchItems, $wmsUrl, $lat, $lng): array {
            $items = $batchItems[$key] ?? [];
            if (is_array($items) && count($items) > 0) {
                return $items;
            }
            if ($layer === '') {
                return [];
            }

            return $this->fetchWmsFeatureInfoAtPoint($wmsUrl, $layer, $lat, $lng);
        };

        $pmItems = $itemsFor('pm25', $pmLayer);
        $no2Items = $itemsFor('no2', $no2Layer);
        $result['pm25_ug_m3'] = $this->extractGrayIndexFromWmsItems($pmItems);
        $result['no2_ug_m3'] = $this->extractGrayIndexFromWmsItems($no2Items);

        if ($geluidLayer !== '') {
            $metric = $this->extractGeluidMetric($itemsFor('geluid', $geluidLayer));
            $result['geluid_omgeving']['waarde'] = $metric['waarde'];
            $result['geluid_omgeving']['score'] = $metric['score'];
        }
        if ($zomerhitteLayer !== '') {
            $metric = $this->extractZomerhitteMetric($itemsFor('zomerhitte', $zomerhitteLayer));
            $result['zomerhitte_stad']['waarde'] = $metric['waarde'];
            $result['zomerhitte_stad']['score'] = $metric['score'];
        }
        if ($overstromingLayer !== '') {
            $metric = $this->extractOverstromingMetric($itemsFor('overstroming', $overstromingLayer));
            $result['kans_op_overstroming']['waarde'] = $metric['waarde'];
            $result['kans_op_overstroming']['score'] = $metric['score'];
        }
        if ($gevaarLayer !== '') {
            $items = $itemsFor('gevaar', $gevaarLayer);
            $metric = $this->extractGevaarlijkeStoffenMetric($items);
            $result['gevaarlijke_stoffen_binnen_1km']['waarde'] = $metric['waarde'] ?? 'Geen';
            $result['gevaarlijke_stoffen_binnen_1km']['score'] = $metric['score'] ?? 1.0;
        }

        return $result;
    }

    private function extractMetricFromWmsItems(array $items): array
    {
        $grayIndex = $this->extractGrayIndexFromWmsItems($items);
        if (is_numeric($grayIndex)) {
            return [
                'waarde' => round((float) $grayIndex, 2),
                'score' => round((float) $grayIndex, 2),
            ];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $value = $this->nullableString($item['value'] ?? null);
            if ($value === null) {
                continue;
            }
            $valueLower = Str::lower($value);
            if (in_array($valueLower, ['n.v.t.', 'niet beschikbaar', 'geen data', 'null'], true)) {
                continue;
            }

            if (is_numeric($value)) {
                return [
                    'waarde' => round((float) $value, 2),
                    'score' => round((float) $value, 2),
                ];
            }

            return [
                'waarde' => $value,
                'score' => null,
            ];
        }

        return [
            'waarde' => null,
            'score' => null,
        ];
    }

    private function extractGeluidMetric(array $items): array
    {
        $metric = $this->extractMetricFromWmsItems($items);
        $score = is_numeric($metric['score'] ?? null) ? round((float) $metric['score'], 1) : null;
        $waarde = is_numeric($metric['waarde'] ?? null) ? round((float) $metric['waarde'], 1) : $metric['waarde'];

        return [
            'waarde' => $waarde,
            'score' => $score,
        ];
    }

    private function extractZomerhitteMetric(array $items): array
    {
        $stressValue = null;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = Str::lower(str_replace([' ', '_'], '', (string) ($item['title'] ?? '')));
            $value = $item['value'] ?? null;
            if ($title === 'stress' && is_numeric($value)) {
                $stressValue = (float) $value;
                break;
            }
        }

        if ($stressValue !== null) {
            $uhi = round($stressValue / 10.0, 1);
            return [
                'waarde' => $uhi,
                'score' => $uhi,
            ];
        }

        return $this->extractMetricFromWmsItems($items);
    }

    private function extractOverstromingMetric(array $items): array
    {
        $grayIndex = $this->extractGrayIndexFromWmsItems($items);
        if (is_numeric($grayIndex)) {
            $index = (int) round((float) $grayIndex);
            return [
                'waarde' => $this->overstromingLabelForIndex($index),
                'score' => $index,
            ];
        }

        $metric = $this->extractMetricFromWmsItems($items);
        if (is_numeric($metric['waarde'] ?? null)) {
            $index = (int) round((float) $metric['waarde']);
            $metric['waarde'] = $this->overstromingLabelForIndex($index);
            $metric['score'] = $index;
        }

        return $metric;
    }

    private function overstromingLabelForIndex(int $index): string
    {
        return match ($index) {
            1, 2 => 'Overstroomt niet / Oppervlaktewater',
            3 => 'Zeer kleine kans',
            4 => 'Kleine kans',
            5 => 'Middelgrote kans',
            default => 'Grote kans',
        };
    }

    private function extractGevaarlijkeStoffenMetric(array $items): array
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = Str::lower(str_replace([' ', '_'], '', (string) ($item['title'] ?? '')));
            $value = $this->nullableString($item['value'] ?? null);
            if ($value === null) {
                continue;
            }

            if (in_array($title, ['activiteit', 'activiteiten'], true)) {
                $normalized = trim($value);
                if ($normalized === '') {
                    continue;
                }

                return [
                    'waarde' => $normalized,
                    'score' => 4.0,
                ];
            }
        }

        if (count($items) === 0) {
            return [
                'waarde' => 'Geen',
                'score' => 1.0,
            ];
        }

        return [
            'waarde' => 'Geen',
            'score' => 1.0,
        ];
    }

    private function extractGrayIndexFromWmsItems(array $items): ?float
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = Str::lower(str_replace([' ', '_'], '', (string) ($item['title'] ?? '')));
            if ($title !== 'grayindex') {
                continue;
            }
            $value = $item['value'] ?? null;
            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    private function detectPoiCategory(array $element): ?string
    {
        $amenity = strtolower(trim((string) data_get($element, 'tags.amenity', '')));
        if ($amenity === 'supermarket') {
            return 'supermarkt';
        }
        if (in_array($amenity, ['sports_centre', 'sports_hall', 'fitness_centre'], true)) {
            return 'sport';
        }
        if ($amenity === 'cafe') {
            return 'cafe';
        }
        if ($amenity === 'restaurant') {
            return 'restaurant';
        }

        $leisure = strtolower(trim((string) data_get($element, 'tags.leisure', '')));
        if (in_array($leisure, ['sports_centre', 'sports_hall', 'fitness_centre', 'pitch', 'stadium'], true)) {
            return 'sport';
        }
        if ($leisure === 'park') {
            return 'groen';
        }

        $natural = strtolower(trim((string) data_get($element, 'tags.natural', '')));
        if (in_array($natural, ['wood', 'grassland', 'heath'], true)) {
            return 'groen';
        }

        $landuse = strtolower(trim((string) data_get($element, 'tags.landuse', '')));
        if (in_array($landuse, ['forest', 'grass'], true)) {
            return 'groen';
        }

        $tourism = strtolower(trim((string) data_get($element, 'tags.tourism', '')));
        if ($tourism === 'hotel') {
            return 'hotel';
        }

        return null;
    }

    private function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

        return $r * $c;
    }

    private function roundDistanceKm(float $distanceMeters): float
    {
        return round($distanceMeters / 1000.0, 2);
    }

    private function buildOsmObjectUrl(?string $type, mixed $id): ?string
    {
        $normalizedType = strtolower(trim((string) $type));
        if (! in_array($normalizedType, ['node', 'way', 'relation'], true)) {
            return null;
        }
        if (! is_numeric($id)) {
            return null;
        }

        return sprintf('https://www.openstreetmap.org/%s/%s', $normalizedType, (string) $id);
    }

    private function fetchNearestTransitDistances(?float $lat, ?float $lng, ?array $preloadedElements = null): array
    {
        $result = [
            'bushalte' => ['afstand_km' => null, 'duur_min' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'station_metro_tram' => ['afstand_km' => null, 'duur_min' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
            'oprit_hoofdweg' => ['afstand_km' => null, 'duur_min' => null, 'naam' => null, 'osm_url' => null, 'lat' => null, 'lng' => null],
        ];

        if ($lat === null || $lng === null) {
            return $result;
        }

        $elements = is_array($preloadedElements) && count($preloadedElements) > 0
            ? $preloadedElements
            : $this->fetchOverpassElementsWithQuery($this->buildTransitOverpassQuery($lat, $lng), false);
        if (! is_array($elements) || count($elements) === 0) {
            return $result;
        }

        $nearestBus = null;
        $nearestRail = null;
        $nearestRamp = null;
        $rampCandidates = [];

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $poiLat = data_get($element, 'lat');
            $poiLng = data_get($element, 'lon');
            if (! is_numeric($poiLat) || ! is_numeric($poiLng)) {
                $poiLat = data_get($element, 'center.lat');
                $poiLng = data_get($element, 'center.lon');
            }
            if (! is_numeric($poiLat) || ! is_numeric($poiLng)) {
                continue;
            }

            $distanceMeters = $this->haversineDistanceMeters($lat, $lng, (float) $poiLat, (float) $poiLng);
            if (! is_finite($distanceMeters)) {
                continue;
            }

            $isBus = strtolower((string) data_get($element, 'tags.highway', '')) === 'bus_stop';
            $isRail = strtolower((string) data_get($element, 'tags.railway', '')) === 'station'
                || strtolower((string) data_get($element, 'tags.railway', '')) === 'tram_stop'
                || strtolower((string) data_get($element, 'tags.station', '')) === 'subway';
            $highwayTag = strtolower((string) data_get($element, 'tags.highway', ''));
            $isRamp = in_array($highwayTag, ['motorway_junction', 'motorway_link'], true);

            if ($isBus && ($nearestBus === null || $distanceMeters < ($nearestBus['distance_m'] ?? INF))) {
                $nearestBus = [
                    'distance_m' => $distanceMeters,
                    'name' => $this->nullableString(data_get($element, 'tags.name')),
                    'type' => (string) data_get($element, 'type', ''),
                    'id' => data_get($element, 'id'),
                    'lat' => (float) $poiLat,
                    'lng' => (float) $poiLng,
                ];
            }

            if ($isRail && ($nearestRail === null || $distanceMeters < ($nearestRail['distance_m'] ?? INF))) {
                $nearestRail = [
                    'distance_m' => $distanceMeters,
                    'name' => $this->nullableString(data_get($element, 'tags.name')),
                    'type' => (string) data_get($element, 'type', ''),
                    'id' => data_get($element, 'id'),
                    'lat' => (float) $poiLat,
                    'lng' => (float) $poiLng,
                ];
            }

            if ($isRamp && ($nearestRamp === null || $distanceMeters < ($nearestRamp['distance_m'] ?? INF))) {
                $nearestRamp = [
                    'distance_m' => $distanceMeters,
                    'name' => $this->nullableString(data_get($element, 'tags.name')),
                    'type' => (string) data_get($element, 'type', ''),
                    'id' => data_get($element, 'id'),
                    'lat' => (float) $poiLat,
                    'lng' => (float) $poiLng,
                ];
            }
            if ($isRamp) {
                $rampCandidates[] = [
                    'distance_m' => $distanceMeters,
                    'name' => $this->nullableString(data_get($element, 'tags.name')),
                    'type' => (string) data_get($element, 'type', ''),
                    'id' => data_get($element, 'id'),
                    'lat' => (float) $poiLat,
                    'lng' => (float) $poiLng,
                ];
            }
        }

        if (is_array($nearestBus) && is_numeric($nearestBus['distance_m'] ?? null)) {
            $result['bushalte'] = [
                'afstand_km' => $this->roundDistanceKm((float) $nearestBus['distance_m']),
                'duur_min' => null,
                'naam' => $nearestBus['name'],
                'osm_url' => $this->buildOsmObjectUrl($nearestBus['type'], $nearestBus['id']),
                'lat' => is_numeric($nearestBus['lat'] ?? null) ? (float) $nearestBus['lat'] : null,
                'lng' => is_numeric($nearestBus['lng'] ?? null) ? (float) $nearestBus['lng'] : null,
            ];
        }
        if (is_array($nearestRail) && is_numeric($nearestRail['distance_m'] ?? null)) {
            $result['station_metro_tram'] = [
                'afstand_km' => $this->roundDistanceKm((float) $nearestRail['distance_m']),
                'duur_min' => null,
                'naam' => $nearestRail['name'],
                'osm_url' => $this->buildOsmObjectUrl($nearestRail['type'], $nearestRail['id']),
                'lat' => is_numeric($nearestRail['lat'] ?? null) ? (float) $nearestRail['lat'] : null,
                'lng' => is_numeric($nearestRail['lng'] ?? null) ? (float) $nearestRail['lng'] : null,
            ];
        }
        if (count($rampCandidates) > 0) {
            usort($rampCandidates, fn (array $a, array $b) => ($a['distance_m'] <=> $b['distance_m']));
            $candidateSubset = array_slice($rampCandidates, 0, 12);
            $bestByDriving = $this->findBestDrivingRampCandidate($lat, $lng, $candidateSubset);
            if (is_array($bestByDriving)) {
                $nearestRamp = $bestByDriving;
            }
        }
        if (is_array($nearestRamp) && is_numeric($nearestRamp['distance_m'] ?? null)) {
            $distanceMeters = is_numeric($nearestRamp['route_distance_m'] ?? null)
                ? (float) $nearestRamp['route_distance_m']
                : (float) $nearestRamp['distance_m'];
            $result['oprit_hoofdweg'] = [
                'afstand_km' => $this->roundDistanceKm($distanceMeters),
                'duur_min' => is_numeric($nearestRamp['route_duration_min'] ?? null)
                    ? (int) max(1, round((float) $nearestRamp['route_duration_min']))
                    : null,
                'naam' => $nearestRamp['name'],
                'osm_url' => $this->buildOsmObjectUrl($nearestRamp['type'], $nearestRamp['id']),
                'lat' => is_numeric($nearestRamp['lat'] ?? null) ? (float) $nearestRamp['lat'] : null,
                'lng' => is_numeric($nearestRamp['lng'] ?? null) ? (float) $nearestRamp['lng'] : null,
            ];
        }

        return $result;
    }

    private function findBestDrivingRampCandidate(float $fromLat, float $fromLng, array $candidates): ?array
    {
        if (count($candidates) === 0) {
            return null;
        }

        $coords = [sprintf('%.7f,%.7f', $fromLng, $fromLat)];
        $normalized = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $lat = $candidate['lat'] ?? null;
            $lng = $candidate['lng'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }
            $normalized[] = $candidate;
            $coords[] = sprintf('%.7f,%.7f', (float) $lng, (float) $lat);
        }

        if (count($normalized) === 0) {
            return null;
        }

        $baseUrls = collect([
            trim((string) config('services.osrm.base_url')),
            'https://router.project-osrm.org',
        ])
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->unique()
            ->values()
            ->all();

        $distances = null;
        $durations = null;
        foreach ($baseUrls as $baseUrl) {
            try {
                $response = Http::timeout(10)
                    ->retry(1, 250)
                    ->get(
                        rtrim($baseUrl, '/').'/table/v1/driving/'.implode(';', $coords),
                        [
                            'sources' => 0,
                            'annotations' => 'distance,duration',
                        ]
                    );
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $candidateDistances = data_get($response->json(), 'distances.0');
            $candidateDurations = data_get($response->json(), 'durations.0');
            if (
                is_array($candidateDistances) && count($candidateDistances) === count($coords)
                && is_array($candidateDurations) && count($candidateDurations) === count($coords)
            ) {
                $distances = $candidateDistances;
                $durations = $candidateDurations;
                break;
            }
        }

        if (! is_array($distances) || ! is_array($durations)) {
            return null;
        }

        $bestIndex = null;
        $bestDistance = INF;
        for ($i = 1; $i < count($distances); $i++) {
            $distance = $distances[$i] ?? null;
            if (! is_numeric($distance)) {
                continue;
            }
            $distance = (float) $distance;
            if (! is_finite($distance) || $distance < 0) {
                continue;
            }
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $i - 1;
            }
        }

        if ($bestIndex === null || ! isset($normalized[$bestIndex])) {
            return null;
        }

        $selected = $normalized[$bestIndex];
        $selected['route_distance_m'] = $bestDistance;
        $selected['route_duration_min'] = is_numeric($durations[$bestIndex + 1] ?? null)
            ? ((float) $durations[$bestIndex + 1] / 60.0)
            : null;

        return $selected;
    }

    private function fetchHeritageByPoint(
        ?float $lat,
        ?float $lng,
        ?string $bagAddress = null,
        ?string $kadastraleAanduiding = null
    ): array
    {
        $base = [
            'monumentenregister_url' => 'https://monumentenregister.cultureelerfgoed.nl/',
            'linkeddata_query_url' => 'https://linkeddata.cultureelerfgoed.nl/rce/cho/sparql',
            'is_monument' => null,
            'is_gemeentelijk_monument' => null,
            'beschermd_stads_dorpsgezicht' => null,
            'rijksmonumenten' => [],
            'gemeentelijke_monumenten' => [],
            'gezichten' => [],
        ];

        if ($lat === null || $lng === null) {
            return $base;
        }

        $pointWkt = sprintf(
            'POINT(%s %s)',
            number_format($lng, 6, '.', ''),
            number_format($lat, 6, '.', '')
        );

        $rijksmonumentQuery = <<<SPARQL
PREFIX ceo: <https://linkeddata.cultureelerfgoed.nl/def/ceo#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX geof: <http://www.opengis.net/def/function/geosparql/>
SELECT DISTINCT ?resource ?nummer ?naam ?detailUrl
WHERE {
  ?resource a ceo:Rijksmonument ;
            ceo:heeftGeometrie ?geom .
  ?geom geo:asWKT ?wkt .
  FILTER(geof:sfIntersects(?wkt, "{$pointWkt}"^^geo:wktLiteral))
  OPTIONAL { ?resource ceo:rijksmonumentnummer ?nummer . }
  OPTIONAL { ?resource ceo:heeftNaam ?naamNode . ?naamNode ceo:naam ?naam . }
  OPTIONAL { ?resource ceo:wordtGetoondOp ?detailUrl . }
}
LIMIT 20
SPARQL;

        $rijksmonumentNearestQuery = <<<SPARQL
PREFIX ceo: <https://linkeddata.cultureelerfgoed.nl/def/ceo#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX geof: <http://www.opengis.net/def/function/geosparql/>
PREFIX uom: <http://www.opengis.net/def/uom/OGC/1.0/>
SELECT DISTINCT ?resource ?nummer ?naam ?detailUrl ?afstandM
WHERE {
  ?resource a ceo:Rijksmonument ;
            ceo:heeftGeometrie ?geom .
  ?geom geo:asWKT ?wkt .
  BIND(geof:distance(?wkt, "{$pointWkt}"^^geo:wktLiteral, uom:metre) AS ?afstandM)
  FILTER(?afstandM <= 50)
  OPTIONAL { ?resource ceo:rijksmonumentnummer ?nummer . }
  OPTIONAL { ?resource ceo:heeftNaam ?naamNode . ?naamNode ceo:naam ?naam . }
  OPTIONAL { ?resource ceo:wordtGetoondOp ?detailUrl . }
}
ORDER BY ASC(?afstandM)
LIMIT 10
SPARQL;

        $gemeentelijkMonumentQuery = <<<SPARQL
PREFIX ceo: <https://linkeddata.cultureelerfgoed.nl/def/ceo#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX geof: <http://www.opengis.net/def/function/geosparql/>
SELECT DISTINCT ?resource ?nummer ?naam ?detailUrl
WHERE {
  ?resource a ceo:GemeentelijkMonument ;
            ceo:heeftGeometrie ?geom .
  ?geom geo:asWKT ?wkt .
  FILTER(geof:sfIntersects(?wkt, "{$pointWkt}"^^geo:wktLiteral))
  OPTIONAL { ?resource ceo:monumentnummer ?nummer . }
  OPTIONAL { ?resource ceo:heeftNaam ?naamNode . ?naamNode ceo:naam ?naam . }
  OPTIONAL { ?resource ceo:wordtGetoondOp ?detailUrl . }
}
LIMIT 20
SPARQL;

        $gezichtQuery = <<<SPARQL
PREFIX ceo: <https://linkeddata.cultureelerfgoed.nl/def/ceo#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX geof: <http://www.opengis.net/def/function/geosparql/>
SELECT DISTINCT ?resource ?nummer ?naam ?registratiedatum ?detailUrl ?typeNaam
WHERE {
  ?resource a ceo:Gezicht ;
            ceo:heeftGeometrie ?geom .
  ?geom geo:asWKT ?wkt .
  FILTER(geof:sfIntersects(?wkt, "{$pointWkt}"^^geo:wktLiteral))
  OPTIONAL { ?resource ceo:gezichtsnummer ?nummer . }
  OPTIONAL { ?resource ceo:heeftNaam ?naamNode . ?naamNode ceo:naam ?naam . }
  OPTIONAL { ?resource ceo:heeftNaam ?typeNode . ?typeNode ceo:naam ?typeNaam . FILTER(CONTAINS(LCASE(STR(?typeNaam)), "gezicht")) }
  OPTIONAL { ?resource ceo:registratiedatum ?registratiedatum . }
  OPTIONAL { ?resource ceo:wordtGetoondOp ?detailUrl . }
}
LIMIT 20
SPARQL;

        $mapRijksmonument = function (array $row): array {
            $resource = $this->nullableString($row['resource'] ?? null);
            $nummer = $this->nullableString($row['nummer'] ?? null);
            if ($nummer === null && $resource !== null) {
                $resourceId = basename(parse_url($resource, PHP_URL_PATH) ?: '');
                if (is_string($resourceId) && preg_match('/^\d+$/', $resourceId) === 1) {
                    $nummer = $resourceId;
                }
            }

            $detailUrl = $this->nullableString($row['detailUrl'] ?? null);
            if ($detailUrl === null && $nummer !== null) {
                $detailUrl = 'https://monumentenregister.cultureelerfgoed.nl/monumenten/'.urlencode($nummer);
            }

            return [
                'resource' => $resource,
                'nummer' => $nummer,
                'naam' => $this->nullableString($row['naam'] ?? null),
                'detail_url' => $detailUrl,
            ];
        };

        $rijksmonumenten = collect($this->runRceSparql($rijksmonumentQuery))
            ->map($mapRijksmonument)
            ->filter(fn (array $row) => $row['resource'] !== null || $row['nummer'] !== null)
            ->unique(fn (array $row) => ($row['resource'] ?? '').'|'.($row['nummer'] ?? ''))
            ->values()
            ->all();

        if (count($rijksmonumenten) === 0) {
            $rijksmonumenten = collect($this->runRceSparql($rijksmonumentNearestQuery))
                ->map($mapRijksmonument)
                ->filter(fn (array $row) => $this->monumentCandidateMatchesBuilding($row, $bagAddress, $kadastraleAanduiding))
                ->filter(fn (array $row) => $row['resource'] !== null || $row['nummer'] !== null)
                ->unique(fn (array $row) => ($row['resource'] ?? '').'|'.($row['nummer'] ?? ''))
                ->values()
                ->all();
        }

        $gemeentelijkeMonumenten = collect($this->runRceSparql($gemeentelijkMonumentQuery))
            ->map(function (array $row): array {
                $resource = $this->nullableString($row['resource'] ?? null);
                $nummer = $this->nullableString($row['nummer'] ?? null);
                $detailUrl = $this->nullableString($row['detailUrl'] ?? null) ?? $resource;

                return [
                    'resource' => $resource,
                    'nummer' => $nummer,
                    'naam' => $this->nullableString($row['naam'] ?? null),
                    'detail_url' => $detailUrl,
                ];
            })
            ->filter(fn (array $row) => $row['resource'] !== null || $row['nummer'] !== null || $row['naam'] !== null)
            ->unique(fn (array $row) => ($row['resource'] ?? '').'|'.($row['nummer'] ?? '').'|'.($row['naam'] ?? ''))
            ->values()
            ->all();

        $gezichten = collect($this->runRceSparql($gezichtQuery))
            ->map(function (array $row): array {
                $resource = $this->nullableString($row['resource'] ?? null);
                $nummer = $this->nullableString($row['nummer'] ?? null);
                $detailUrl = $this->nullableString($row['detailUrl'] ?? null);
                if ($detailUrl === null && $nummer !== null) {
                    $cleanNummer = Str::startsWith(strtoupper($nummer), 'BG') ? substr($nummer, 2) : $nummer;
                    $detailUrl = 'https://archisarchief.cultureelerfgoed.nl/Beschermde_Gezichten/BG'.urlencode($cleanNummer).'/';
                }
                if ($detailUrl === null) {
                    $detailUrl = $resource;
                }

                return [
                    'resource' => $resource,
                    'nummer' => $nummer,
                    'naam' => $this->nullableString($row['naam'] ?? null),
                    'type' => $this->nullableString($row['typeNaam'] ?? null) ?? 'Beschermd gezicht',
                    'registratiedatum' => $this->nullableString($row['registratiedatum'] ?? null),
                    'detail_url' => $detailUrl,
                ];
            })
            ->filter(fn (array $row) => $row['resource'] !== null || $row['nummer'] !== null)
            ->unique(fn (array $row) => ($row['resource'] ?? '').'|'.($row['nummer'] ?? ''))
            ->values()
            ->all();

        $base['rijksmonumenten'] = $rijksmonumenten;
        $base['gemeentelijke_monumenten'] = $gemeentelijkeMonumenten;
        $base['gezichten'] = $gezichten;
        $base['is_monument'] = count($rijksmonumenten) > 0;
        $base['is_gemeentelijk_monument'] = count($gemeentelijkeMonumenten) > 0;
        $base['beschermd_stads_dorpsgezicht'] = count($gezichten) > 0;

        return $base;
    }

    private function monumentCandidateMatchesBuilding(
        array $candidate,
        ?string $bagAddress,
        ?string $kadastraleAanduiding
    ): bool {
        $name = $this->nullableString($candidate['naam'] ?? null);
        $detailUrl = $this->nullableString($candidate['detail_url'] ?? null);
        $resource = $this->nullableString($candidate['resource'] ?? null);
        $nummer = $this->nullableString($candidate['nummer'] ?? null);

        $haystacks = collect([$name, $detailUrl, $resource, $nummer])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $this->normalizeMonumentMatchText($value))
            ->filter(fn (string $value) => $value !== '')
            ->values()
            ->all();

        if ($haystacks === []) {
            return false;
        }

        $addressMatches = false;
        $address = $this->nullableString($bagAddress);
        if ($address !== null) {
            $addressMatches = $this->monumentAddressMatchesHaystacks($address, $haystacks);
        }

        $cadastreMatches = false;
        $cadastre = $this->nullableString($kadastraleAanduiding);
        if ($cadastre !== null) {
            $needle = $this->normalizeMonumentMatchText($cadastre);
            if ($needle !== '') {
                foreach ($haystacks as $haystack) {
                    if (str_contains($haystack, $needle)) {
                        $cadastreMatches = true;
                        break;
                    }
                }
            }
        }

        return $addressMatches || $cadastreMatches;
    }

    private function monumentAddressMatchesHaystacks(string $bagAddress, array $haystacks): bool
    {
        $normalizedAddress = $this->normalizeMonumentMatchText($bagAddress);
        if ($normalizedAddress === '') {
            return false;
        }

        preg_match('/^\s*(.+?)\s+(\d+[a-zA-Z0-9\-\/]*)\b/u', $bagAddress, $parts);
        $street = isset($parts[1]) ? $this->normalizeMonumentMatchText((string) $parts[1]) : '';
        $houseNumber = isset($parts[2]) ? $this->normalizeMonumentMatchText((string) $parts[2]) : '';

        foreach ($haystacks as $haystack) {
            if ($street !== '' && $houseNumber !== '') {
                if (str_contains($haystack, $street) && str_contains($haystack, $houseNumber)) {
                    return true;
                }
            }

            if (str_contains($haystack, $normalizedAddress)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMonumentMatchText(string $value): string
    {
        $value = Str::lower(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
    }

    private function runRceSparql(string $query): array
    {
        $endpoint = trim((string) config('services.rce.sparql_url'));
        if ($endpoint === '') {
            return [];
        }

        $response = Http::timeout(15)
            ->retry(1, 250)
            ->get($endpoint, [
                'query' => $query,
                'format' => 'application/sparql-results+json',
            ]);

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return [];
        }

        // Endpoint returns either SPARQL JSON format or a flattened list of rows.
        if (array_is_list($payload)) {
            return collect($payload)
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all();
        }

        $bindings = data_get($payload, 'results.bindings', []);
        if (! is_array($bindings)) {
            return [];
        }

        return collect($bindings)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $flat = [];
                foreach ($row as $key => $value) {
                    if (is_array($value) && isset($value['value']) && is_string($value['value'])) {
                        $flat[$key] = $value['value'];
                    }
                }

                return $flat;
            })
            ->values()
            ->all();
    }

    private function extractUrlList(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $url) => trim($url))
            ->filter(fn (string $url) => filter_var($url, FILTER_VALIDATE_URL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function bagRequestErrorMessage(?Response $response, string $default): string
    {
        if (! $response) {
            return $default;
        }

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            return 'BAG API-key is ongeldig of heeft geen toegang.';
        }

        return $default;
    }

    private function pdokAddressSuggestions(string $query): array
    {
        $response = Http::timeout(8)
            ->retry(1, 250)
            ->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/suggest', [
                'q' => $query,
                'rows' => 10,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $docs = collect(data_get($response->json(), 'response.docs', []))
            ->filter(fn ($doc) => is_array($doc) && ($doc['type'] ?? null) === 'adres')
            ->values()
            ->all();

        return collect($docs)
            ->map(function (array $doc) {
                $lookupId = $this->nullableString($doc['id'] ?? null);
                if (! $lookupId) {
                    return null;
                }

                $lookup = $this->fetchPdokAddressById($lookupId);
                if (! $lookup) {
                    return null;
                }

                return [
                    'id' => 'pdok:'.$lookupId,
                    'address' => $lookup['address'],
                    'city' => $lookup['city'],
                    'postcode' => $lookup['postcode'],
                    'label' => trim($lookup['address'].', '.trim(($lookup['postcode'] ?? '').' '.$lookup['city'])),
                ];
            })
            ->filter()
            ->unique(fn (array $item) => $item['id'])
            ->values()
            ->all();
    }

    private function fetchPdokAddressById(string $lookupId): ?array
    {
        $response = Http::timeout(8)
            ->retry(1, 250)
            ->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/lookup', [
                'id' => $lookupId,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $doc = data_get($response->json(), 'response.docs.0');
        if (! is_array($doc)) {
            return null;
        }

        $street = $this->nullableString($doc['straatnaam'] ?? null);
        $house = $this->nullableString($doc['huis_nlt'] ?? null)
            ?? $this->nullableString($doc['huisnummer'] ?? null);
        $city = $this->nullableString($doc['woonplaatsnaam'] ?? null);
        if (! $street || ! $house || ! $city) {
            return null;
        }

        return [
            'address' => trim($street.' '.$house),
            'city' => $city,
            'postcode' => $this->nullableString($doc['postcode'] ?? null),
            'nummeraanduiding_id' => $this->nullableString($doc['nummeraanduiding_id'] ?? null),
            'adresseerbaarobject_id' => $this->nullableString($doc['adresseerbaarobject_id'] ?? null),
            'centroide_ll' => $this->nullableString($doc['centroide_ll'] ?? null),
            'centroide_rd' => $this->nullableString($doc['centroide_rd'] ?? null),
        ];
    }

    private function fetchPdokAddressExtendedById(string $lookupId): ?array
    {
        $pdok = $this->fetchPdokAddressById($lookupId);
        if (! $pdok) {
            return null;
        }

        $rdCoordinates = null;
        $rdWkt = $pdok['centroide_rd'] ?? null;
        if (is_string($rdWkt) && preg_match('/POINT\\(([-\\d\\.]+)\\s+([-\\d\\.]+)\\)/i', $rdWkt, $matches)) {
            $x = isset($matches[1]) ? (float) $matches[1] : null;
            $y = isset($matches[2]) ? (float) $matches[2] : null;
            if (is_finite($x) && is_finite($y)) {
                $rdCoordinates = [$x, $y, 0.0];
            }
        }

        $details = [
            'pand_identificaties' => [],
            'bouwjaar' => null,
            'gebruiksfunctie' => null,
            'oppervlakte_m2' => null,
            'adresseerbaar_object_status' => null,
            'geometry_rd' => null,
            'energielabel' => null,
        ];

        $nummeraanduidingId = $pdok['nummeraanduiding_id'] ?? null;
        if (is_string($nummeraanduidingId) && $nummeraanduidingId !== '') {
            $extendedResponse = $this->bagRequest(
                'adressenuitgebreid/'.urlencode($nummeraanduidingId),
                [],
                ['Accept-Crs' => 'epsg:28992']
            );
            if ($extendedResponse && $extendedResponse->successful()) {
                $details = $this->extractBagExtendedDetails((array) $extendedResponse->json());
            }
        }

        return [
            'address' => $pdok['address'],
            'city' => $pdok['city'],
            'postcode' => $pdok['postcode'] ?? '',
            'nummeraanduiding_identificatie' => $pdok['nummeraanduiding_id'],
            'adresseerbaar_object_identificatie' => $pdok['adresseerbaarobject_id'],
            'pand_identificaties' => $details['pand_identificaties'],
            'bouwjaar' => $details['bouwjaar'],
            'gebruiksfunctie' => $details['gebruiksfunctie'],
            'oppervlakte_m2' => $details['oppervlakte_m2'],
            'adresseerbaar_object_status' => $details['adresseerbaar_object_status'],
            'geometry_rd' => is_array($details['geometry_rd']) ? $details['geometry_rd'] : $rdCoordinates,
            'geometry_ll' => $pdok['centroide_ll'],
            'energielabel' => $details['energielabel'],
        ];
    }

    private function extractBagExtendedDetails(array $extended): array
    {
        $bouwjaarRaw = $this->findFirstScalarByKeys($extended, ['oorspronkelijkBouwjaar', 'bouwjaar']);
        $bouwjaar = $this->hasFilledValue($bouwjaarRaw) ? trim((string) $bouwjaarRaw) : null;

        $gebruiksdoelen = $this->collectStringValuesByKeys($extended, ['gebruiksdoelen', 'gebruiksdoel', 'gebruiksfunctie']);
        $gebruiksfunctie = count($gebruiksdoelen) > 0 ? implode(', ', array_values(array_unique($gebruiksdoelen))) : null;

        $oppervlakteRaw = $this->findFirstNumericByKeys($extended, ['oppervlakte', 'oppervlakteM2', 'oppervlakte_m2']);
        $oppervlakte = $oppervlakteRaw !== null ? (int) $oppervlakteRaw : null;

        $energielabelRaw = $this->findFirstScalarByKeys($extended, ['energielabel', 'energieLabel', 'label_energie', 'energyLabel']);
        $energielabel = $this->hasFilledValue($energielabelRaw) ? trim((string) $energielabelRaw) : null;

        $pandIdentificaties = $this->collectStringValuesByKeys(
            $extended,
            ['pandIdentificaties', 'pandIdentificatie', 'pand_id', 'pandId', 'pand']
        );
        $pandIdentificaties = collect($pandIdentificaties)
            ->filter(fn ($value) => preg_match('/^\d{16}$/', $value) === 1)
            ->values()
            ->all();

        $geometryRd = $this->findFirstCoordinatePairByKeys($extended, ['coordinates', 'coordinaten']);

        return [
            'pand_identificaties' => $pandIdentificaties,
            'bouwjaar' => $bouwjaar,
            'gebruiksfunctie' => $gebruiksfunctie,
            'oppervlakte_m2' => $oppervlakte,
            'adresseerbaar_object_status' => $this->findFirstScalarByKeys($extended, ['adresseerbaarObjectStatus', 'status']),
            'geometry_rd' => $geometryRd,
            'energielabel' => $energielabel,
        ];
    }

    private function enrichBagCoreData(array $bag, ?float $lat, ?float $lng): array
    {
        $supplemental = $this->fetchBagSupplementalByObjectId(
            is_string($bag['adresseerbaar_object_identificatie'] ?? null)
                ? $bag['adresseerbaar_object_identificatie']
                : null,
            is_array($bag['pand_identificaties'] ?? null) ? $bag['pand_identificaties'] : []
        );
        $linkedDataSupplemental = $this->fetchBagLinkedDataSupplemental(
            is_string($bag['adresseerbaar_object_identificatie'] ?? null)
                ? $bag['adresseerbaar_object_identificatie']
                : null,
            is_string($bag['nummeraanduiding_identificatie'] ?? null)
                ? $bag['nummeraanduiding_identificatie']
                : null
        );

        if (! $this->hasFilledValue($bag['pand_identificaties'] ?? null) && $this->hasFilledValue($supplemental['pand_identificaties'] ?? null)) {
            $bag['pand_identificaties'] = $supplemental['pand_identificaties'];
        }
        if (! $this->hasFilledValue($bag['pand_identificaties'] ?? null) && $this->hasFilledValue($linkedDataSupplemental['pand_identificaties'] ?? null)) {
            $bag['pand_identificaties'] = $linkedDataSupplemental['pand_identificaties'];
        }
        if (! $this->hasFilledValue($bag['bouwjaar'] ?? null) && $this->hasFilledValue($supplemental['bouwjaar'] ?? null)) {
            $bag['bouwjaar'] = $supplemental['bouwjaar'];
        }
        if (! $this->hasFilledValue($bag['bouwjaar'] ?? null) && $this->hasFilledValue($linkedDataSupplemental['bouwjaar'] ?? null)) {
            $bag['bouwjaar'] = $linkedDataSupplemental['bouwjaar'];
        }
        if (! $this->hasFilledValue($bag['gebruiksfunctie'] ?? null) && $this->hasFilledValue($supplemental['gebruiksfunctie'] ?? null)) {
            $bag['gebruiksfunctie'] = $supplemental['gebruiksfunctie'];
        }
        if (! $this->hasFilledValue($bag['gebruiksfunctie'] ?? null) && $this->hasFilledValue($linkedDataSupplemental['gebruiksfunctie'] ?? null)) {
            $bag['gebruiksfunctie'] = $linkedDataSupplemental['gebruiksfunctie'];
        }
        if (! $this->hasFilledValue($bag['oppervlakte_m2'] ?? null) && $this->hasFilledValue($supplemental['oppervlakte_m2'] ?? null)) {
            $bag['oppervlakte_m2'] = $supplemental['oppervlakte_m2'];
        }
        if (! $this->hasFilledValue($bag['oppervlakte_m2'] ?? null) && $this->hasFilledValue($linkedDataSupplemental['oppervlakte_m2'] ?? null)) {
            $bag['oppervlakte_m2'] = $linkedDataSupplemental['oppervlakte_m2'];
        }
        if (! $this->hasFilledValue($bag['energielabel'] ?? null) && $this->hasFilledValue($supplemental['energielabel'] ?? null)) {
            $bag['energielabel'] = $supplemental['energielabel'];
        }

        if (! $this->hasFilledValue($bag['energielabel'] ?? null)) {
            $bag['energielabel'] = $this->fetchEnergielabelAtPoint($lat, $lng);
        }

        return $bag;
    }

    private function fetchBagLinkedDataSupplemental(?string $adresseerbaarObjectId, ?string $nummeraanduidingId): array
    {
        $result = [
            'pand_identificaties' => [],
            'bouwjaar' => null,
            'gebruiksfunctie' => null,
            'oppervlakte_m2' => null,
        ];

        $verblijfsobjectId = $adresseerbaarObjectId;
        if (! $verblijfsobjectId && $nummeraanduidingId) {
            $nummerPayload = $this->fetchBagLinkedDataNode('nummeraanduiding', $nummeraanduidingId);
            if (is_array($nummerPayload)) {
                $verblijfsRef = data_get($nummerPayload, 'bag:ligtAan.0.@id')
                    ?? data_get($nummerPayload, 'bag:ligtAan.@id')
                    ?? data_get($nummerPayload, 'bag:adresseerbaarObject.0.@id')
                    ?? data_get($nummerPayload, 'bag:adresseerbaarObject.@id');
                if (is_string($verblijfsRef) && preg_match('/(\d{16})$/', $verblijfsRef, $m)) {
                    $verblijfsobjectId = $m[1];
                }
            }
        }

        if (! is_string($verblijfsobjectId) || trim($verblijfsobjectId) === '') {
            return $result;
        }

        $verblijfsNode = $this->fetchBagLinkedDataNode('verblijfsobject', $verblijfsobjectId);
        if (! is_array($verblijfsNode)) {
            return $result;
        }

        $oppervlakte = data_get($verblijfsNode, 'bag:oppervlakte.@value') ?? data_get($verblijfsNode, 'bag:oppervlakte');
        if (is_numeric($oppervlakte)) {
            $result['oppervlakte_m2'] = (int) $oppervlakte;
        }

        $gebruiksdoelRefs = [
            data_get($verblijfsNode, 'bag:gebruiksdoel.@id'),
            ...((array) data_get($verblijfsNode, 'bag:gebruiksdoel.*.@id', [])),
        ];
        $gebruiksdoelen = collect($gebruiksdoelRefs)
            ->filter(fn ($ref) => is_string($ref) && trim($ref) !== '')
            ->map(function (string $ref) {
                $name = preg_replace('~^.*/~', '', $ref);
                return preg_replace('/(?<!^)([A-Z])/', ' $1', $name ?? '');
            })
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->values()
            ->all();
        if (count($gebruiksdoelen) > 0) {
            $result['gebruiksfunctie'] = implode(', ', array_values(array_unique($gebruiksdoelen)));
        }

        $pandRef = data_get($verblijfsNode, 'bag:maaktDeelUitVan.@id')
            ?? data_get($verblijfsNode, 'bag:maaktDeelUitVan.0.@id');
        $pandId = null;
        if (is_string($pandRef) && preg_match('/(\d{16})$/', $pandRef, $m)) {
            $pandId = $m[1];
            $result['pand_identificaties'] = [$pandId];
        }

        if ($pandId) {
            $pandNode = $this->fetchBagLinkedDataNode('pand', $pandId);
            if (is_array($pandNode)) {
                $bouwjaar = data_get($pandNode, 'bag:oorspronkelijkBouwjaar.@value')
                    ?? data_get($pandNode, 'bag:oorspronkelijkBouwjaar');
                if (is_scalar($bouwjaar) && trim((string) $bouwjaar) !== '') {
                    $result['bouwjaar'] = trim((string) $bouwjaar);
                }
            }
        }

        return $result;
    }

    private function fetchBagLinkedDataNode(string $type, string $id): ?array
    {
        $url = sprintf('https://bag.basisregistraties.overheid.nl/bag/id/%s/%s', trim($type), trim($id));
        $response = Http::timeout(10)
            ->retry(1, 250)
            ->withHeaders(['Accept' => 'application/ld+json'])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $decoded = json_decode($response->body(), true);
            $payload = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($payload)) {
            return null;
        }

        $nodes = $this->flattenLinkedDataGraphNodes(data_get($payload, '@graph'));
        if (count($nodes) === 0) {
            return null;
        }

        foreach ($nodes as $node) {
            $nodeId = data_get($node, '@id');
            if (is_string($nodeId) && str_ends_with($nodeId, '/'.trim($id))) {
                return $node;
            }
        }

        return is_array($nodes[0] ?? null) ? $nodes[0] : null;
    }

    private function flattenLinkedDataGraphNodes(mixed $graph): array
    {
        if (! is_array($graph)) {
            return [];
        }

        $result = [];
        $queue = array_values($graph);

        while (count($queue) > 0) {
            $node = array_shift($queue);
            if (! is_array($node)) {
                continue;
            }

            if (is_array(data_get($node, '@graph'))) {
                foreach (data_get($node, '@graph', []) as $nested) {
                    $queue[] = $nested;
                }
            }

            if (is_string(data_get($node, '@id'))) {
                $result[] = $node;
            }
        }

        return $result;
    }

    private function fetchBagSupplementalByObjectId(?string $adresseerbaarObjectId, array $existingPandIds): array
    {
        $result = [
            'pand_identificaties' => $existingPandIds,
            'bouwjaar' => null,
            'gebruiksfunctie' => null,
            'oppervlakte_m2' => null,
            'energielabel' => null,
        ];

        if (! is_string($adresseerbaarObjectId) || trim($adresseerbaarObjectId) === '') {
            return $result;
        }

        $response = $this->bagRequest('adresseerbareobjecten/'.urlencode($adresseerbaarObjectId));
        if (! $response || ! $response->successful()) {
            return $result;
        }

        $payload = (array) $response->json();
        $object = $this->extractFirstArrayByKeys($payload, [
            'verblijfsobject',
            'standplaats',
            'ligplaats',
            'adresseerbaarObject',
            'adresseerbaarobject',
        ]) ?? $payload;

        $oppervlakte = data_get($object, 'oppervlakte', data_get($payload, 'oppervlakte'));
        if (is_numeric($oppervlakte)) {
            $result['oppervlakte_m2'] = (int) $oppervlakte;
        }

        $gebruiksdoelen = data_get($object, 'gebruiksdoelen', data_get($payload, 'gebruiksdoelen', []));
        if (! is_array($gebruiksdoelen)) {
            $gebruiksdoelen = $this->hasFilledValue($gebruiksdoelen) ? [ (string) $gebruiksdoelen ] : [];
        }
        $gebruiksdoelen = collect($gebruiksdoelen)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => trim($item))
            ->values()
            ->all();
        if (count($gebruiksdoelen) > 0) {
            $result['gebruiksfunctie'] = implode(', ', $gebruiksdoelen);
        }

        $pandIds = collect(data_get($object, 'pandIdentificaties', data_get($payload, 'pandIdentificaties', [])))
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => trim($item))
            ->values()
            ->all();
        if (count($pandIds) === 0) {
            $pandIds = $existingPandIds;
        }
        $result['pand_identificaties'] = $pandIds;

        $energielabelRaw = data_get($object, 'energielabel', data_get($payload, 'energielabel'));
        if ($energielabelRaw === null) {
            $energielabelRaw = data_get($object, 'energieLabel', data_get($payload, 'energieLabel'));
        }
        if ($this->hasFilledValue($energielabelRaw)) {
            $result['energielabel'] = trim((string) $energielabelRaw);
        }

        foreach (array_slice($pandIds, 0, 3) as $pandId) {
            $pandResponse = $this->bagRequest('panden/'.urlencode($pandId));
            if (! $pandResponse || ! $pandResponse->successful()) {
                continue;
            }

            $pandPayload = (array) $pandResponse->json();
            $pand = $this->extractFirstArrayByKeys($pandPayload, ['pand']) ?? $pandPayload;
            $bouwjaar = data_get($pand, 'oorspronkelijkBouwjaar', data_get($pandPayload, 'oorspronkelijkBouwjaar'));
            if ($this->hasFilledValue($bouwjaar)) {
                $result['bouwjaar'] = (string) $bouwjaar;
                break;
            }
        }

        return $result;
    }

    private function fetchEnergielabelAtPoint(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $items = $this->fetchWmsFeatureInfoAtPoint(
            (string) config('services.pdok.energielabel_wms_url'),
            (string) config('services.pdok.energielabel_wms_layer'),
            $lat,
            $lng
        );

        $normalized = collect($items)
            ->filter(fn ($item) => is_array($item))
            ->keyBy(function (array $item) {
                $title = is_string($item['title'] ?? null) ? $item['title'] : '';
                return Str::lower(str_replace([' ', '_'], '', $title));
            });

        $label = data_get($normalized->get('dominantlabel'), 'value')
            ?? data_get($normalized->get('hoogstelabel'), 'value')
            ?? data_get($normalized->get('laagstelabel'), 'value');

        return $this->hasFilledValue($label) ? trim((string) $label) : null;
    }

    private function hasFilledValue(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) > 0;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }

    private function extractFirstArrayByKeys(array $payload, array $keys): ?array
    {
        foreach ($keys as $key) {
            $candidate = data_get($payload, $key);
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function findFirstScalarByKeys(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $values = $this->collectValuesByKeyRecursive($payload, $key);
            foreach ($values as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return $value;
                }
                if (is_array($value)) {
                    foreach ($value as $nested) {
                        if (is_scalar($nested) && trim((string) $nested) !== '') {
                            return $nested;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function findFirstNumericByKeys(array $payload, array $keys): ?float
    {
        foreach ($keys as $key) {
            $values = $this->collectValuesByKeyRecursive($payload, $key);
            foreach ($values as $value) {
                if (is_numeric($value)) {
                    return (float) $value;
                }
                if (is_array($value)) {
                    foreach ($value as $nested) {
                        if (is_numeric($nested)) {
                            return (float) $nested;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function collectStringValuesByKeys(array $payload, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            foreach ($this->collectValuesByKeyRecursive($payload, $key) as $rawValue) {
                $values = array_merge($values, $this->normalizeToStringList($rawValue));
            }
        }

        return collect($values)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->values()
            ->all();
    }

    private function normalizeToStringList(mixed $value): array
    {
        if (is_scalar($value)) {
            return [trim((string) $value)];
        }
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $result[] = trim((string) $item);
                continue;
            }
            if (is_array($item)) {
                foreach (['identificatie', 'id', 'waarde', 'value', 'code', 'naam'] as $candidateKey) {
                    $candidate = $item[$candidateKey] ?? null;
                    if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                        $result[] = trim((string) $candidate);
                    }
                }
            }
        }

        return $result;
    }

    private function findFirstCoordinatePairByKeys(array $payload, array $keys): ?array
    {
        foreach ($keys as $key) {
            foreach ($this->collectValuesByKeyRecursive($payload, $key) as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                if (count($candidate) >= 2 && is_numeric($candidate[0] ?? null) && is_numeric($candidate[1] ?? null)) {
                    return [(float) $candidate[0], (float) $candidate[1]];
                }

                foreach ($candidate as $nested) {
                    if (is_array($nested) && count($nested) >= 2 && is_numeric($nested[0] ?? null) && is_numeric($nested[1] ?? null)) {
                        return [(float) $nested[0], (float) $nested[1]];
                    }
                }
            }
        }

        return null;
    }

    private function collectValuesByKeyRecursive(mixed $node, string $targetKey): array
    {
        if (! is_array($node)) {
            return [];
        }

        $results = [];
        foreach ($node as $key => $value) {
            if ((string) $key === $targetKey) {
                $results[] = $value;
            }
            if (is_array($value)) {
                $results = array_merge($results, $this->collectValuesByKeyRecursive($value, $targetKey));
            }
        }

        return $results;
    }
}
