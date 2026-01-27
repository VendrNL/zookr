<?php

namespace App\Http\Controllers;

use App\Models\SearchRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SearchRequestController extends Controller
{
    private const PROPERTY_TYPES = [
        'kantoorruimte',
        'bedrijfsruimte',
        'logistiek',
        'winkelruimte',
        'recreatief_vastgoed',
        'maatschappelijk_vastgoed',
        'buitenterrein',
    ];

    private const PROVINCES = [
        'groningen',
        'friesland',
        'drenthe',
        'overijssel',
        'flevoland',
        'gelderland',
        'utrecht',
        'noord_holland',
        'zuid_holland',
        'zeeland',
        'noord_brabant',
        'limburg',
    ];

    private const ACQUISITIONS = [
        'huur',
        'koop',
    ];
    public function index(Request $request)
    {
        $this->authorize('viewAny', SearchRequest::class);

        $query = SearchRequest::query()
            ->with([
                'creator:id,name,email,organization_id',
                'organization:id,name,logo_path',
                'assignee:id,name,email',
            ]);

        // Filters
        $status = array_values(array_filter((array) $request->input('status', [])));
        $status = array_values(array_intersect($status, ['concept', 'open', 'afgerond', 'geannuleerd']));
        if ($status !== []) {
            $query->whereIn('status', $status);
        }

        $province = array_values(array_filter((array) $request->input('province', [])));
        $province = array_values(array_intersect($province, self::PROVINCES));
        if ($province !== []) {
            $query->where(function ($sub) use ($province) {
                foreach ($province as $item) {
                    $sub->orWhereJsonContains('provinces', $item);
                }
            });
        }

        $propertyType = array_values(array_filter((array) $request->input('property_type', [])));
        $propertyType = array_values(array_intersect($propertyType, self::PROPERTY_TYPES));
        if ($propertyType !== []) {
            $query->whereIn('property_type', $propertyType);
        }

        $q = $request->string('q')->toString();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        if ($sort === 'title') {
            $query->orderBy('title', $direction);
        } elseif ($sort === 'organization') {
            $query->leftJoin('organizations', 'search_requests.organization_id', '=', 'organizations.id')
                ->orderBy('organizations.name', $direction)
                ->select('search_requests.*');
        } elseif ($sort === 'created_at') {
            $query->orderBy('created_at', $direction);
        } else {
            $query->latest();
        }

        return Inertia::render('SearchRequests/Index', [
            'filters' => [
                'status' => $status,
                'q' => $q,
                'province' => $province,
                'property_type' => $propertyType,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'items' => $query->paginate(25)->withQueryString(),
            'can' => [
                'create' => $request->user()->can('create', SearchRequest::class),
                'is_admin' => (bool) $request->user()->is_admin,
            ],
            'options' => [
                'types' => self::PROPERTY_TYPES,
                'provinces' => self::PROVINCES,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', SearchRequest::class);

        return Inertia::render('SearchRequests/Create', [
            'options' => [
                'types' => self::PROPERTY_TYPES,
                'provinces' => self::PROVINCES,
                'acquisitions' => self::ACQUISITIONS,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SearchRequest::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'customer_name' => ['required', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:150'],
            'provinces' => ['required', 'array', 'min:1'],
            'provinces.*' => ['string', Rule::in(self::PROVINCES)],
            'property_type' => ['required', 'string', Rule::in(self::PROPERTY_TYPES)],
            'surface_area' => ['required', 'string', 'max:150'],
            'parking' => ['nullable', 'string', 'max:150'],
            'availability' => ['required', 'string', 'max:150'],
            'accessibility' => ['nullable', 'string', 'max:150'],
            'acquisitions' => ['required', 'array', 'min:1'],
            'acquisitions.*' => ['string', Rule::in(self::ACQUISITIONS)],
            'notes' => ['nullable', 'string', 'max:800'],
            'send' => ['nullable', 'boolean'],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['organization_id'] = $request->user()->organization_id;
        $send = $request->boolean('send');
        $data['status'] = $send ? 'open' : 'concept';

        if (! $data['organization_id']) {
            abort(403, 'Deze gebruiker heeft geen Makelaar gekoppeld.');
        }

        $item = SearchRequest::create($data);

        if ($send) {
            return redirect()->route('search-requests.recipients', $item);
        }

        return redirect()->route('search-requests.show', $item);
    }

    public function show(Request $request, SearchRequest $search_request)
    {
        $this->authorize('view', $search_request);

        $search_request->load([
            'creator:id,name,email,organization_id',
            'organization:id,name,logo_path',
            'assignee:id,name,email',
        ]);

        $organizationId = $request->user()->organization_id;
        $canViewAllOffers = $request->user()->is_admin
            || ($organizationId && (int) $organizationId === (int) $search_request->organization_id);
        $offeredProperties = collect();
        if ($organizationId) {
            $query = Property::query()
                ->where('search_request_id', $search_request->id);

            if (! $canViewAllOffers) {
                $query->where('organization_id', $organizationId);
            }

            $relations = [
                'user:id,name,avatar_path',
                'contactUser:id,name,avatar_path',
            ];

            if ($canViewAllOffers) {
                $relations[] = 'organization:id,name';
            }

            $offeredProperties = $query
                ->with($relations)
                ->latest()
                ->get([
                    'id',
                    'organization_id',
                    'user_id',
                    'contact_user_id',
                    'search_request_id',
                    'name',
                    'address',
                    'city',
                    'surface_area',
                    'availability',
                    'acquisition',
                    'rent_price_per_m2',
                    'rent_price_parking',
                    'created_at',
                ]);
        }

        return Inertia::render('SearchRequests/Show', [
            'item' => $search_request,
            'offeredProperties' => $offeredProperties,
            'tab' => $request->string('tab')->toString(),
            'viewAllOffers' => $canViewAllOffers,
            'can' => [
                'update' => $request->user()->can('update', $search_request),
                'assign' => $request->user()->can('assign', $search_request),
                'delete' => $request->user()->can('delete', $search_request),
                'offer' => $request->user()->can('offer', $search_request),
                'viewAllOffers' => $canViewAllOffers,
            ],
        ]);
    }

    public function recipients(Request $request, SearchRequest $search_request)
    {
        $this->authorize('view', $search_request);

        $search_request->load('organization:id,name');

        $provinces = $search_request->provinces ?? [];
        $propertyType = $search_request->property_type;

        $users = User::query()
            ->with(['organization:id,name'])
            ->when($propertyType, function ($query) use ($propertyType) {
                $query->whereJsonContains('specialism_types', $propertyType);
            })
            ->when(! empty($provinces), function ($query) use ($provinces) {
                $query->where(function ($sub) use ($provinces) {
                    foreach ($provinces as $province) {
                        $sub->orWhereJsonContains('specialism_provinces', $province);
                    }
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        return Inertia::render('SearchRequests/Recipients', [
            'item' => $search_request,
            'users' => $users,
        ]);
    }

    public function edit(Request $request, SearchRequest $search_request)
    {
        $this->authorize('update', $search_request);

        return Inertia::render('SearchRequests/Edit', [
            'item' => $search_request->load(['assignee:id,name,email']),
            'options' => [
                'types' => self::PROPERTY_TYPES,
                'provinces' => self::PROVINCES,
                'acquisitions' => self::ACQUISITIONS,
            ],
        ]);
    }

    public function update(Request $request, SearchRequest $search_request)
    {
        $this->authorize('update', $search_request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'customer_name' => ['required', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:150'],
            'provinces' => ['required', 'array', 'min:1'],
            'provinces.*' => ['string', Rule::in(self::PROVINCES)],
            'property_type' => ['required', 'string', Rule::in(self::PROPERTY_TYPES)],
            'surface_area' => ['required', 'string', 'max:150'],
            'parking' => ['nullable', 'string', 'max:150'],
            'availability' => ['required', 'string', 'max:150'],
            'accessibility' => ['nullable', 'string', 'max:150'],
            'acquisitions' => ['required', 'array', 'min:1'],
            'acquisitions.*' => ['string', Rule::in(self::ACQUISITIONS)],
            'notes' => ['nullable', 'string', 'max:800'],
            'send' => ['nullable', 'boolean'],
        ]);

        $send = $request->boolean('send');
        $data['status'] = $send ? 'open' : 'concept';

        $search_request->update($data);

        if ($send) {
            return redirect()->route('search-requests.recipients', $search_request);
        }

        return redirect()->route('search-requests.show', $search_request);
    }

    public function destroy(Request $request, SearchRequest $search_request)
    {
        $this->authorize('delete', $search_request);

        $search_request->delete();

        return redirect()->route('search-requests.index');
    }

    // Optioneel (admin): assign endpoint via aparte route
    public function assign(Request $request, SearchRequest $search_request)
    {
        $this->authorize('assign', $search_request);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $search_request->update([
            'assigned_to' => $data['assigned_to'],
        ]);

        return redirect()->route('search-requests.show', $search_request);
    }

    // Optioneel: status quick action
    public function setStatus(Request $request, SearchRequest $search_request)
    {
        $this->authorize('update', $search_request);

        $data = $request->validate([
            'status' => ['required', 'in:concept,open,afgerond,geannuleerd'],
        ]);

        $search_request->update(['status' => $data['status']]);

        return redirect()->route('search-requests.show', $search_request);
    }
}

