<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SearchRequest;
use App\Models\User;
use App\Services\Funda\ScrapeFundaBusinessService;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
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
            'bag_address_id' => ['required', 'string', 'max:128'],
        ]);

        $isPdokAddress = Str::startsWith($data['bag_address_id'], 'pdok:');
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
        ];

        $bag = $this->fetchAddressExtendedByIdentifier($data['bag_address_id']);
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
            $geocode = $this->geocodeFromBagGeometry($bag['geometry_rd'] ?? null);
            $diagnostics['geocode_fallback_bag'] = [
                'status' => $geocode ? 'ok' : 'failed',
                'detail' => $geocode
                    ? 'Geocode afgeleid uit BAG geometrie (RD -> WGS84).'
                    : 'Geen BAG geometrie beschikbaar voor fallback.',
            ];
        }

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

        $zoningPlanObjects = $this->fetchZoningPlanObjectsByPoint(
            $geocode['lat'] ?? null,
            $geocode['lng'] ?? null
        );
        if (! $geocode) {
            $diagnostics['pdok_zoning'] = [
                'status' => 'skipped',
                'detail' => 'Overgeslagen: geen geocode beschikbaar.',
            ];
        } else {
            $diagnostics['pdok_zoning'] = [
                'status' => count($zoningPlanObjects) > 0 ? 'ok' : 'no_data',
                'detail' => count($zoningPlanObjects) > 0
                    ? 'Planobjecten gevonden via PDOK Ruimtelijke Plannen.'
                    : 'Geen planobject op exact punt (of PDOK gaf geen bruikbare response).',
            ];
        }

        $heritage = $this->fetchHeritageByPoint(
            $geocode['lat'] ?? null,
            $geocode['lng'] ?? null
        );
        if (! $geocode) {
            $diagnostics['rce_heritage'] = [
                'status' => 'skipped',
                'detail' => 'Overgeslagen: geen geocode beschikbaar.',
            ];
        } else {
            $hasHeritage = count($heritage['rijksmonumenten'] ?? []) > 0 || count($heritage['gezichten'] ?? []) > 0;
            $diagnostics['rce_heritage'] = [
                'status' => $hasHeritage ? 'ok' : 'no_data',
                'detail' => $hasHeritage
                    ? 'Monument/gezicht gevonden via RCE Linked Data.'
                    : 'Geen monument/gezicht op exact punt (of query gaf geen resultaat).',
            ];
        }

        return response()->json([
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
                'energielabel' => null,
            ],
            'geocode' => $geocode,
            'cadastre' => $parcel,
            'zoning' => [
                'wkbp_beschikbaar' => count($zoningPlanObjects) > 0,
                'omgevingsplan_url' => 'https://omgevingswet.overheid.nl/regels-op-de-kaart',
                'planobjecten' => $zoningPlanObjects,
                'toelichting' => count($zoningPlanObjects) > 0
                    ? 'Planobjecten op deze locatie zijn opgehaald via PDOK Ruimtelijke Plannen (WMS GetFeatureInfo).'
                    : 'Geen planobject gevonden op exact punt. Controleer de kaartlink voor omliggende plannen.',
            ],
            'heritage' => $heritage,
            'soil' => [
                'bodemloket_url' => 'https://www.bodemloket.nl/kaart',
                'status' => null,
            ],
            'accessibility' => [
                'ov' => null,
                'auto' => null,
                'afstand_tot_knooppunten' => null,
                'bronnen' => [
                    'https://www.pdok.nl/',
                    'https://www.cbs.nl/',
                ],
            ],
            'woz' => [
                'beschikbaar_via_open_api' => false,
                'toelichting' => 'WOZ Waardeloket ondersteunt geen algemene open bulk-API voor geautomatiseerde verrijking.',
                'waardeloket_url' => 'https://www.wozwaardeloket.nl/',
            ],
            'diagnostics' => $diagnostics,
            'map' => [
                'google_maps_api_key_available' => (string) config('services.google_maps.api_key') !== '',
                'google_maps_api_key' => (string) config('services.google_maps.api_key'),
                'kadastraal_wms_url' => (string) config('services.pdok.kadastraal_wms_url'),
                'kadastraal_wms_layer' => 'KadastraleGrens',
                'bodemkaart_wms_url' => (string) config('services.pdok.bodemkaart_wms_url'),
                'bodemkaart_wms_layer' => (string) config('services.pdok.bodemkaart_wms_layer'),
            ],
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

        $bouwjaar = data_get($extended, 'oorspronkelijkBouwjaar');
        if (is_array($bouwjaar)) {
            $bouwjaar = count($bouwjaar) ? (string) $bouwjaar[0] : null;
        } else {
            $bouwjaar = $bouwjaar !== null && $bouwjaar !== '' ? (string) $bouwjaar : null;
        }

        $gebruiksdoelen = collect(data_get($extended, 'gebruiksdoelen', []))
            ->filter(fn ($item) => is_string($item) && $item !== '')
            ->values()
            ->all();

        $oppervlakte = data_get($extended, 'oppervlakte');
        $oppervlakte = is_numeric($oppervlakte) ? (int) $oppervlakte : null;

        return [
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'nummeraanduiding_identificatie' => $nummeraanduidingIdentificatie,
            'adresseerbaar_object_identificatie' => $adresseerbaarObjectIdentificatie !== '' ? $adresseerbaarObjectIdentificatie : null,
            'pand_identificaties' => $pandIdentificaties,
            'bouwjaar' => $bouwjaar,
            'gebruiksfunctie' => count($gebruiksdoelen) ? implode(', ', $gebruiksdoelen) : null,
            'oppervlakte_m2' => $oppervlakte,
            'adresseerbaar_object_status' => data_get($extended, 'adresseerbaarObjectStatus'),
            'geometry_rd' => data_get($extended, 'adresseerbaarObjectGeometrie.punt.coordinates'),
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

    private function fetchParcelByPoint(?float $lat, ?float $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $delta = 0.0002;
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
                'limit' => 1,
                'f' => 'json',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $properties = data_get($response->json(), 'features.0.properties');
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

        return [
            'kadastrale_aanduiding' => $aanduiding,
            'perceelsgrootte_m2' => isset($properties['kadastrale_grootte_waarde']) && is_numeric($properties['kadastrale_grootte_waarde'])
                ? (int) $properties['kadastrale_grootte_waarde']
                : null,
            'identificatie_lokaal_id' => $properties['identificatie_lokaal_id'] ?? null,
        ];
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

    private function fetchHeritageByPoint(?float $lat, ?float $lng): array
    {
        $base = [
            'monumentenregister_url' => 'https://monumentenregister.cultureelerfgoed.nl/',
            'linkeddata_query_url' => 'https://linkeddata.cultureelerfgoed.nl/rce/cho/sparql',
            'is_monument' => null,
            'beschermd_stads_dorpsgezicht' => null,
            'rijksmonumenten' => [],
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

        $gezichtQuery = <<<SPARQL
PREFIX ceo: <https://linkeddata.cultureelerfgoed.nl/def/ceo#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX geof: <http://www.opengis.net/def/function/geosparql/>
SELECT DISTINCT ?resource ?nummer ?naam ?registratiedatum ?detailUrl
WHERE {
  ?resource a ceo:Gezicht ;
            ceo:heeftGeometrie ?geom .
  ?geom geo:asWKT ?wkt .
  FILTER(geof:sfIntersects(?wkt, "{$pointWkt}"^^geo:wktLiteral))
  OPTIONAL { ?resource ceo:gezichtsnummer ?nummer . }
  OPTIONAL { ?resource ceo:heeftNaam ?naamNode . ?naamNode ceo:naam ?naam . }
  OPTIONAL { ?resource ceo:registratiedatum ?registratiedatum . }
  OPTIONAL { ?resource ceo:wordtGetoondOp ?detailUrl . }
}
LIMIT 20
SPARQL;

        $rijksmonumenten = collect($this->runRceSparql($rijksmonumentQuery))
            ->map(fn (array $row) => [
                'resource' => $this->nullableString($row['resource'] ?? null),
                'nummer' => $this->nullableString($row['nummer'] ?? null),
                'naam' => $this->nullableString($row['naam'] ?? null),
                'detail_url' => $this->nullableString($row['detailUrl'] ?? null),
            ])
            ->filter(fn (array $row) => $row['resource'] !== null || $row['nummer'] !== null)
            ->unique(fn (array $row) => ($row['resource'] ?? '').'|'.($row['nummer'] ?? ''))
            ->values()
            ->all();

        $gezichten = collect($this->runRceSparql($gezichtQuery))
            ->map(fn (array $row) => [
                'resource' => $this->nullableString($row['resource'] ?? null),
                'nummer' => $this->nullableString($row['nummer'] ?? null),
                'naam' => $this->nullableString($row['naam'] ?? null),
                'registratiedatum' => $this->nullableString($row['registratiedatum'] ?? null),
                'detail_url' => $this->nullableString($row['detailUrl'] ?? null),
            ])
            ->filter(fn (array $row) => $row['resource'] !== null || $row['nummer'] !== null)
            ->unique(fn (array $row) => ($row['resource'] ?? '').'|'.($row['nummer'] ?? ''))
            ->values()
            ->all();

        $base['rijksmonumenten'] = $rijksmonumenten;
        $base['gezichten'] = $gezichten;
        $base['is_monument'] = count($rijksmonumenten) > 0;
        $base['beschermd_stads_dorpsgezicht'] = count($gezichten) > 0;

        return $base;
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

        return [
            'address' => $pdok['address'],
            'city' => $pdok['city'],
            'postcode' => $pdok['postcode'] ?? '',
            'nummeraanduiding_identificatie' => $pdok['nummeraanduiding_id'],
            'adresseerbaar_object_identificatie' => $pdok['adresseerbaarobject_id'],
            'pand_identificaties' => [],
            'bouwjaar' => null,
            'gebruiksfunctie' => null,
            'oppervlakte_m2' => null,
            'adresseerbaar_object_status' => null,
            'geometry_rd' => $rdCoordinates,
            'geometry_ll' => $pdok['centroide_ll'],
        ];
    }
}
