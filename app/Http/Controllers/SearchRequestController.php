<?php

namespace App\Http\Controllers;

use App\Models\SearchRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SearchRequest::class);

        $user = $request->user();

        $query = SearchRequest::query()
            ->with([
                'creator:id,name,email,organization_name',
                'assignee:id,name,email',
            ])
            ->latest();

        // Niet-admin: alleen eigen aanvragen of aan jou toegewezen
        if (! $user->is_admin) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }

        // Filters
        $status = $request->string('status')->toString();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $q = $request->string('q')->toString();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        return Inertia::render('SearchRequests/Index', [
            'filters' => [
                'status' => $status,
                'q' => $q,
            ],
            'items' => $query->paginate(15)->withQueryString(),
            'can' => [
                'create' => $user->can('create', SearchRequest::class),
                'is_admin' => (bool) $user->is_admin,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', SearchRequest::class);

        return Inertia::render('SearchRequests/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', SearchRequest::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status'] = 'open';

        $item = SearchRequest::create($data);

        return redirect()->route('search-requests.show', $item);
    }

    public function show(Request $request, SearchRequest $search_request)
    {
        $this->authorize('view', $search_request);

        $search_request->load(['creator:id,name,email', 'assignee:id,name,email']);

        return Inertia::render('SearchRequests/Show', [
            'item' => $search_request,
            'can' => [
                'update' => $request->user()->can('update', $search_request),
                'assign' => $request->user()->can('assign', $search_request),
                'delete' => $request->user()->can('delete', $search_request),
            ],
        ]);
    }

    public function edit(Request $request, SearchRequest $search_request)
    {
        $this->authorize('update', $search_request);

        return Inertia::render('SearchRequests/Edit', [
            'item' => $search_request->load(['assignee:id,name,email']),
        ]);
    }

    public function update(Request $request, SearchRequest $search_request)
    {
        $this->authorize('update', $search_request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:open,in_behandeling,afgerond,geannuleerd'],
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
            'status' => ['required', 'in:open,in_behandeling,afgerond,geannuleerd'],
        ]);

        $search_request->update(['status' => $data['status']]);

        return redirect()->route('search-requests.show', $search_request);
    }
}
