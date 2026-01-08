<?php

namespace App\Http\Controllers;

use App\Models\SearchRequest;
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
            ])
            ->latest();

        // Filters
        $status = $request->string('status')->toString();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $province = $request->string('province')->toString();
        if ($province !== '') {
            $query->whereJsonContains('provinces', $province);
        }

        $propertyType = $request->string('property_type')->toString();
        if ($propertyType !== '') {
            $query->where('property_type', $propertyType);
        }

        $q = $request->string('q')->toString();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        return Inertia::render('SearchRequests/Index', [
            'filters' => [
                'status' => $status,
                'q' => $q,
                'province' => $province,
                'property_type' => $propertyType,
            ],
            'items' => $query->paginate(15)->withQueryString(),
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
            abort(403, 'Deze gebruiker heeft geen organisatie gekoppeld.');
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

        return Inertia::render('SearchRequests/Show', [
            'item' => $search_request,
            'can' => [
                'update' => $request->user()->can('update', $search_request),
                'assign' => $request->user()->can('assign', $search_request),
                'delete' => $request->user()->can('delete', $search_request),
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
            'status' => ['required', 'in:concept,open,afgerond,geannuleerd'],
        ]);

        $search_request->update($data);

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
