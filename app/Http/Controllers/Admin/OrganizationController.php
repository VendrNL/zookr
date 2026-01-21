<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'all';
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $organizationsQuery = Organization::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->withCount('users');

        if ($status === 'active') {
            $organizationsQuery->where('is_active', true);
        } elseif ($status === 'inactive') {
            $organizationsQuery->where('is_active', false);
        }

        if ($sort === 'name') {
            $organizationsQuery->orderBy('name', $direction);
        } else {
            $organizationsQuery->orderBy('name');
        }

        $organizations = $organizationsQuery
            ->paginate(25)
            ->withQueryString()
            ->through(function (Organization $organization) {
                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'phone' => $organization->phone,
                    'email' => $organization->email,
                    'website' => $organization->website,
                    'logo_url' => $organization->logo_url,
                    'members_count' => $organization->users_count,
                    'is_active' => (bool) $organization->is_active,
                ];
            });

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function edit(Organization $organization)
    {
        $members = $organization->users()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'linkedin_url',
                'avatar_path',
                'is_active',
            ]);

        return Inertia::render('Admin/Organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'phone' => $organization->phone,
                'email' => $organization->email,
                'website' => $organization->website,
                'logo_path' => $organization->logo_path,
                'logo_url' => $organization->logo_url,
                'is_active' => (bool) $organization->is_active,
            ],
            'members' => $members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'linkedin_url' => $member->linkedin_url,
                    'avatar_url' => $member->avatar_url,
                    'is_active' => $member->is_active === null
                        ? true
                        : (bool) $member->is_active,
                ];
            }),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Organizations/Create');
    }

    public function importForm()
    {
        return Inertia::render('Admin/Organizations/Import');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $data['file']->getRealPath();
        if (! $path || ! file_exists($path)) {
            return Redirect::back()->withErrors([
                'file' => 'Bestand kon niet worden gelezen.',
            ]);
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return Redirect::back()->withErrors([
                'file' => 'Bestand kon niet worden geopend.',
            ]);
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            return Redirect::back()->withErrors([
                'file' => 'Bestand is leeg.',
            ]);
        }

        $delimiter = collect([",", ";", "\t", "|"])
            ->mapWithKeys(fn ($char) => [$char => substr_count($headerLine, $char)])
            ->sortDesc()
            ->keys()
            ->first();

        $stripBom = static fn (string $value): string => ltrim($value, "\xEF\xBB\xBF");

        $headers = str_getcsv($stripBom($headerLine), $delimiter);
        $headers = array_map(static fn ($header) => strtolower(trim($header)), $headers);

        $columns = [
            'organization_name' => null,
            'organization_phone' => null,
            'organization_email' => null,
            'organization_website' => null,
        ];

        foreach ($headers as $index => $header) {
            if (array_key_exists($header, $columns)) {
                $columns[$header] = $index;
            }
        }

        if ($columns['organization_name'] === null) {
            fclose($handle);
            return Redirect::back()->withErrors([
                'file' => 'Kolom "Organization_name" ontbreekt.',
            ]);
        }

        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $name = trim((string) ($row[$columns['organization_name']] ?? ''));
            if ($name === '') {
                continue;
            }

            if (Organization::where('name', $name)->exists()) {
                $skipped += 1;
                continue;
            }

            $phone = trim((string) ($row[$columns['organization_phone']] ?? '')) ?: null;
            $email = trim((string) ($row[$columns['organization_email']] ?? '')) ?: null;
            $website = trim((string) ($row[$columns['organization_website']] ?? '')) ?: null;

            Organization::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'is_active' => true,
            ]);

            $created += 1;
        }

        fclose($handle);

        return Redirect::route('admin.organizations.index')
            ->with('status', "Import voltooid. Nieuw: {$created}, overgeslagen: {$skipped}.");
    }

    public function store(Request $request)
    {
        $websiteInput = trim((string) $request->input('website', ''));
        if ($websiteInput !== '' && ! str_starts_with($websiteInput, 'http://') && ! str_starts_with($websiteInput, 'https://')) {
            $request->merge([
                'website' => 'https://' . ltrim($websiteInput, '/'),
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['required', 'url', 'max:255'],
            'logo' => ['required', 'image', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);

        $logoPath = $data['logo']->store('logos', 'public');

        $organization = Organization::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'website' => $data['website'],
            'logo_path' => $logoPath,
            'is_active' => (bool) $data['is_active'],
        ]);

        return Redirect::route('admin.organizations.edit', $organization)
            ->with('status', 'organization-created');
    }

    public function update(Request $request, Organization $organization)
    {
        $websiteInput = trim((string) $request->input('website', ''));
        if ($websiteInput !== '' && ! str_starts_with($websiteInput, 'http://') && ! str_starts_with($websiteInput, 'https://')) {
            $request->merge([
                'website' => 'https://' . ltrim($websiteInput, '/'),
            ]);
        }

        $data = $request->validate([
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('organizations', 'name')->ignore($organization->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $logoPath = $organization->logo_path;

        if (isset($data['logo'])) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $data['logo']->store('logos', 'public');
        }

        $organization->name = $data['name'] ?? null;
        $organization->phone = $data['phone'] ?? null;
        $organization->email = $data['email'] ?? null;
        $organization->website = $data['website'] ?? null;
        $organization->logo_path = $logoPath;
        if (array_key_exists('is_active', $data)) {
            $organization->is_active = (bool) $data['is_active'];
        }
        $organization->save();

        if (array_key_exists('is_active', $data)) {
            $organization->users()
                ->update(['is_active' => (bool) $data['is_active']]);
        }

        return Redirect::route('admin.organizations.index')
            ->with('status', 'organization-updated');
    }

    public function setStatus(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $organization->update([
            'is_active' => (bool) $data['is_active'],
        ]);

        $organization->users()
            ->update(['is_active' => (bool) $data['is_active']]);

        return Redirect::back();
    }
}
