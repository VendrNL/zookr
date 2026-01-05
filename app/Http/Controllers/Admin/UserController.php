<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{
    private const SPECIALISM_TYPES = [
        'kantoorruimte',
        'bedrijfsruimte',
        'logistiek',
        'winkelruimte',
        'recreatief_vastgoed',
        'maatschappelijk_vastgoed',
        'buitenterrein',
    ];

    private const SPECIALISM_PROVINCES = [
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

    public function edit(Request $request, User $user)
    {
        $returnTo = $this->resolveReturnTo($request->query('return_to'));

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'linkedin_url' => $user->linkedin_url,
                'avatar_url' => $user->avatar_url,
                'is_active' => (bool) $user->is_active,
                'is_admin' => (bool) $user->is_admin,
            ],
            'specialism' => [
                'selection' => [
                    'types' => $user->specialism_types ?? [],
                    'provinces' => $user->specialism_provinces ?? [],
                ],
                'options' => [
                    'types' => self::SPECIALISM_TYPES,
                    'provinces' => self::SPECIALISM_PROVINCES,
                ],
            ],
            'return_to' => $returnTo,
        ]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:active,inactive,all'],
            'admin' => ['nullable', 'in:1,0,all'],
            'sort' => ['nullable', 'in:name,organization'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $status = $data['status'] ?? 'active';
        $admin = $data['admin'] ?? 'all';
        $sort = $data['sort'] ?? 'name';
        $direction = $data['direction'] ?? 'asc';

        $query = User::query();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($admin === '1') {
            $query->where('is_admin', true);
        } elseif ($admin === '0') {
            $query->where('is_admin', false);
        }

        if ($sort === 'organization') {
            $query->orderBy('organization_name', $direction)
                ->orderBy('name', 'asc');
        } else {
            $query->orderBy('name', $direction);
        }

        $users = $query->get([
            'id',
            'name',
            'email',
            'organization_name',
            'is_admin',
            'is_active',
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'status' => $status,
                'admin' => $admin,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $returnTo = $this->resolveReturnTo($request->input('return_to'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
        ]);

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('is_admin', $data)) {
            $user->is_admin = (bool) $data['is_admin'];
        }

        if (($data['remove_avatar'] ?? false) && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $path;
        }

        $user->save();

        return Redirect::to($returnTo)->with('status', 'user-updated');
    }

    public function updateSpecialism(Request $request, User $user)
    {
        $returnTo = $this->resolveReturnTo($request->input('return_to'));

        $data = $request->validate([
            'types' => ['array'],
            'types.*' => ['string', \Illuminate\Validation\Rule::in(self::SPECIALISM_TYPES)],
            'provinces' => ['array'],
            'provinces.*' => ['string', \Illuminate\Validation\Rule::in(self::SPECIALISM_PROVINCES)],
        ]);

        $user->specialism_types = $data['types'] ?? [];
        $user->specialism_provinces = $data['provinces'] ?? [];
        $user->save();

        return Redirect::route('admin.users.edit', [
            'user' => $user->id,
            'return_to' => $returnTo,
        ])->with('status', 'specialism-updated');
    }

    private function resolveReturnTo(?string $returnTo): string
    {
        if (is_string($returnTo) && Str::startsWith($returnTo, '/admin/organizations')) {
            return $returnTo;
        }

        return route('admin.organizations.index');
    }
}
