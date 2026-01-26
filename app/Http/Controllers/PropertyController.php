<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SearchRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
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
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'max:5120'],
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

    public function edit(Request $request, SearchRequest $search_request, Property $property)
    {
        $this->authorize('update', $property);

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
}
