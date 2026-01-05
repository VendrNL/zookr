<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->whereNotNull('organization_name')
            ->orderBy('organization_name')
            ->orderBy('id')
            ->get([
                'id',
                'organization_name',
                'organization_phone',
                'organization_email',
                'organization_website',
                'is_active',
            ]);

        $organizations = $users
            ->groupBy('organization_name')
            ->map(function ($group) {
                $representative = $group->first();
                $isActive = $group->every(function ($member) {
                    return $member->is_active !== false;
                });

                return [
                    'id' => $representative->id,
                    'name' => $representative->organization_name,
                    'phone' => $representative->organization_phone,
                    'email' => $representative->organization_email,
                    'website' => $representative->organization_website,
                    'members_count' => $group->count(),
                    'is_active' => $isActive,
                ];
            })
            ->values();

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function edit(User $user)
    {
        if (! $user->organization_name) {
            abort(404);
        }

        $organizationName = $user->organization_name;

        $members = User::query()
            ->where('organization_name', $organizationName)
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

        $isActive = ! User::query()
            ->where('organization_name', $organizationName)
            ->where('is_active', false)
            ->exists();

        return Inertia::render('Admin/Organizations/Edit', [
            'organization' => [
                'id' => $user->id,
                'name' => $organizationName,
                'phone' => $user->organization_phone,
                'email' => $user->organization_email,
                'website' => $user->organization_website,
                'logo_path' => $user->organization_logo_path,
                'logo_url' => $user->organization_logo_path
                    ? Storage::disk('public')->url($user->organization_logo_path)
                    : null,
                'is_active' => $isActive,
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

    public function update(Request $request, User $user)
    {
        if (! $user->organization_name) {
            abort(404);
        }

        $organizationName = $user->organization_name;

        $websiteInput = trim((string) $request->input('website', ''));
        if ($websiteInput !== '' && ! str_starts_with($websiteInput, 'http://') && ! str_starts_with($websiteInput, 'https://')) {
            $request->merge([
                'website' => 'https://' . ltrim($websiteInput, '/'),
            ]);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $logoPath = $user->organization_logo_path;

        if (isset($data['logo'])) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $data['logo']->store('logos', 'public');
        }

        $payload = [
            'organization_name' => $data['name'] ?? null,
            'organization_phone' => $data['phone'] ?? null,
            'organization_email' => $data['email'] ?? null,
            'organization_website' => $data['website'] ?? null,
            'organization_logo_path' => $logoPath,
        ];

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        User::query()
            ->where('organization_name', $organizationName)
            ->update($payload);

        return Redirect::route('admin.organizations.index')
            ->with('status', 'organization-updated');
    }
}
