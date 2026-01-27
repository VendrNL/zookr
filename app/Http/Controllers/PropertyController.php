<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SearchRequest;
use App\Models\User;
use App\Services\Funda\ScrapeFundaBusinessService;
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

    public function store(Request $request, SearchRequest $search_request)
    {
        $this->authorize('offer', $search_request);

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
}
