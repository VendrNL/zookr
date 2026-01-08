<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;
        $members = $organization
            ? $organization->users()->orderBy('name')->get([
                'id',
                'name',
                'email',
                'phone',
                'linkedin_url',
                'avatar_path',
                'is_active',
            ])
            : collect();

        return Inertia::render('Organization/Edit', [
            'organization' => [
                'id' => $organization?->id,
                'name' => $organization?->name,
                'phone' => $organization?->phone,
                'email' => $organization?->email,
                'website' => $organization?->website,
                'logo_path' => $organization?->logo_path,
                'logo_url' => $organization?->logo_path
                    ? Storage::disk('public')->url($organization->logo_path)
                    : null,
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

    public function update(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization ?? new Organization();

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
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        if (($data['remove_logo'] ?? false) && $organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
            $organization->logo_path = null;
        }

        if (isset($data['logo'])) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $path = $data['logo']->store('logos', 'public');
            $organization->logo_path = $path;
        }

        $organization->name = $data['name'] ?? null;
        $organization->phone = $data['phone'] ?? null;
        $organization->email = $data['email'] ?? null;
        $organization->website = $data['website'] ?? null;

        $organization->save();

        if (! $user->organization_id) {
            $user->organization_id = $organization->id;
            $user->save();
        }

        return Redirect::route('organization.edit')->with('status', 'organization-updated');
    }

    public function createUser(Request $request)
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            abort(403);
        }

        return Inertia::render('Organization/Users/Create', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }

    public function storeUser(Request $request)
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'invite' => ['nullable', 'boolean'],
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(32)),
            'phone' => $data['phone'],
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'is_active' => true,
            'is_admin' => false,
            'organization_id' => $organization->id,
            'avatar_path' => $avatarPath,
        ]);

        if (! empty($data['invite'])) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return Redirect::route('organization.edit')->with('status', 'user-created');
    }

    public function editUser(Request $request, User $user)
    {
        $organization = $request->user()->organization;

        if (! $organization || (int) $user->organization_id !== (int) $organization->id) {
            abort(403);
        }

        return Inertia::render('Organization/Users/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'linkedin_url' => $user->linkedin_url,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $organization = $request->user()->organization;

        if (! $organization || (int) $user->organization_id !== (int) $organization->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return Redirect::route('organization.edit')->with('status', 'user-updated');
    }
}
