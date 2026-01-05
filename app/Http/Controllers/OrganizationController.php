<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Organization/Edit', [
            'organization' => [
                'name' => $user->organization_name,
                'phone' => $user->organization_phone,
                'email' => $user->organization_email,
                'website' => $user->organization_website,
                'logo_path' => $user->organization_logo_path,
                'logo_url' => $user->organization_logo_path
                    ? Storage::disk('public')->url($user->organization_logo_path)
                    : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        if (($data['remove_logo'] ?? false) && $user->organization_logo_path) {
            Storage::disk('public')->delete($user->organization_logo_path);
            $user->organization_logo_path = null;
        }

        if (isset($data['logo'])) {
            if ($user->organization_logo_path) {
                Storage::disk('public')->delete($user->organization_logo_path);
            }
            $path = $data['logo']->store('logos', 'public');
            $user->organization_logo_path = $path;
        }

        $user->organization_name = $data['name'] ?? null;
        $user->organization_phone = $data['phone'] ?? null;
        $user->organization_email = $data['email'] ?? null;
        $user->organization_website = $data['website'] ?? null;

        $user->save();

        return Redirect::route('organization.edit')->with('status', 'organization-updated');
    }
}
