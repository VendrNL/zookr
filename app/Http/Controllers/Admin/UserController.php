<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Notifications\AccountCreatedNotification;

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
                'organization_id' => $user->organization_id,
            ],
            'organizations' => Organization::query()
                ->orderBy('name')
                ->get(['id', 'name']),
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

    public function create(?Organization $organization = null)
    {
        return Inertia::render('Admin/Users/Create', [
            'organization' => $organization
                ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ]
                : null,
            'specialism' => [
                'selection' => [
                    'types' => self::SPECIALISM_TYPES,
                    'provinces' => self::SPECIALISM_PROVINCES,
                ],
                'options' => [
                    'types' => self::SPECIALISM_TYPES,
                    'provinces' => self::SPECIALISM_PROVINCES,
                ],
            ],
            'organizations' => Organization::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['required', 'boolean'],
            'is_admin' => ['required', 'boolean'],
            'invite' => ['nullable', 'boolean'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(self::SPECIALISM_TYPES)],
            'provinces' => ['nullable', 'array'],
            'provinces.*' => ['string', Rule::in(self::SPECIALISM_PROVINCES)],
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $types = $data['types'] ?? self::SPECIALISM_TYPES;
        $provinces = $data['provinces'] ?? self::SPECIALISM_PROVINCES;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(32)),
            'phone' => $data['phone'],
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'is_admin' => (bool) $data['is_admin'],
            'organization_id' => $organization->id,
            'specialism_types' => $types,
            'specialism_provinces' => $provinces,
            'avatar_path' => $avatarPath,
        ]);

        if (! empty($data['invite'])) {
            $token = Password::broker()->createToken($user);
            $user->notify(new AccountCreatedNotification($token, $organization->name));
        }

        return Redirect::route('admin.users.index')
            ->with('status', 'user-created');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:active,inactive,all'],
            'sort' => ['nullable', 'in:name,organization'],
            'direction' => ['nullable', 'in:asc,desc'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $data['status'] ?? 'active';
        $sort = $data['sort'] ?? 'name';
        $direction = $data['direction'] ?? 'asc';
        $search = trim((string) ($data['q'] ?? ''));

        $query = User::query()
            ->leftJoin('organizations', 'users.organization_id', '=', 'organizations.id');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%')
                    ->orWhere('organizations.name', 'like', '%' . $search . '%');
            });
        }

        if ($status === 'active') {
            $query->where('users.is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('users.is_active', false);
        }

        if ($sort === 'organization') {
            $query->orderBy('organizations.name', $direction)
                ->orderBy('users.name', 'asc');
        } else {
            $query->orderBy('users.name', $direction);
        }

        $users = $query
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.avatar_path',
                'users.is_admin',
                'users.is_active',
                'users.linkedin_url',
                'organizations.id as organization_id',
                'organizations.name as organization_name',
            ])
            ->paginate(25)
            ->withQueryString()
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_path
                        ? Storage::disk('public')->url($user->avatar_path)
                        : null,
                    'is_admin' => (bool) $user->is_admin,
                    'is_active' => (bool) $user->is_active,
                    'linkedin_url' => $user->linkedin_url,
                    'organization_id' => $user->organization_id,
                    'organization_name' => $user->organization_name,
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'q' => $search,
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
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'organization_id' => $data['organization_id'] ?? null,
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

    public function setStatus(Request $request, User $user)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update([
            'is_active' => (bool) $data['is_active'],
        ]);

        return Redirect::back();
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

    public function sendPasswordReset(User $user)
    {
        Password::sendResetLink(['email' => $user->email]);

        return Redirect::back()->with('status', 'password-reset-sent');
    }

    public function destroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return Redirect::back()
                ->with('status', 'user-delete-self-denied');
        }

        $user->delete();

        return Redirect::route('admin.users.index')
            ->with('status', 'user-deleted');
    }

    private function resolveReturnTo(?string $returnTo): string
    {
        if (is_string($returnTo) && Str::startsWith($returnTo, ['/admin/makelaars', '/admin/users'])) {
            return $returnTo;
        }

        return route('admin.users.index');
    }
}
