<?php

use App\Http\Controllers\SearchRequestController;
use App\Http\Controllers\PropertyController;
use App\Models\Organization;
use App\Models\SearchRequest;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\SpecialismController;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Web routes voor de Zookr applicatie
*/

// Root: toon landing page (Home)
Route::get('/', function () {
    $searchRequests = SearchRequest::query()
        ->where('status', 'open')
        ->with([
            'organization:id,name,logo_path',
            'creator:id,name,avatar_path',
        ])
        ->latest()
        ->take(10)
        ->get([
            'id',
            'organization_id',
            'created_by',
            'title',
            'location',
            'provinces',
            'property_type',
            'surface_area',
            'acquisitions',
            'created_at',
        ])
        ->map(function (SearchRequest $item) {
            $logoPath = $item->organization?->logo_path;

            return [
                'id' => $item->id,
                'title' => $item->title,
                'location' => $item->location,
                'provinces' => $item->provinces,
                'property_type' => $item->property_type,
                'surface_area' => $item->surface_area,
                'acquisitions' => $item->acquisitions,
                'created_at' => $item->created_at,
                'organization' => [
                    'name' => $item->organization?->name,
                    'logo_url' => $logoPath
                        ? Storage::disk('public')->url($logoPath)
                        : null,
                ],
                'contact' => [
                    'name' => $item->creator?->name,
                    'avatar_url' => $item->creator?->avatar_url,
                ],
            ];
        });
    $tickerOrganizations = Organization::query()
        ->where('is_active', true)
        ->whereNotNull('logo_path')
        ->orderBy('name')
        ->get(['id', 'name', 'logo_path']);

    return Inertia::render('Home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'searchRequests' => $searchRequests,
        'tickerOrganizations' => $tickerOrganizations,
    ]);
});

Route::get('/gebruiksvoorwaarden', function () {
    return Inertia::render('Legal/Terms');
})->name('legal.terms');

Route::get('/privacyverklaring', function () {
    return Inertia::render('Legal/Privacy');
})->name('legal.privacy');

Route::get('/contact', function () {
    return Inertia::render('Legal/Contact');
})->name('legal.contact');

// Dashboard (legacy redirect naar zoekvragen)
Route::get('/dashboard', function () {
    return redirect()->route('search-requests.index');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Profielbeheer (breeze default)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::get('/makelaardij', [OrganizationController::class, 'edit'])
        ->name('makelaardij.edit');
    Route::post('/makelaardij', [OrganizationController::class, 'update'])
        ->name('makelaardij.update');
    Route::get('/makelaardij/users/create', [OrganizationController::class, 'createUser'])
        ->name('makelaardij.users.create');
    Route::post('/makelaardij/users', [OrganizationController::class, 'storeUser'])
        ->name('makelaardij.users.store');
    Route::get('/makelaardij/users/{user}', [OrganizationController::class, 'editUser'])
        ->name('makelaardij.users.edit');
    Route::patch('/makelaardij/users/{user}', [OrganizationController::class, 'updateUser'])
        ->name('makelaardij.users.update');
    Route::patch('/makelaardij/users/{user}/status', [OrganizationController::class, 'setUserStatus'])
        ->name('makelaardij.users.status');

    Route::get('/specialism', [SpecialismController::class, 'edit'])
        ->name('specialism.edit');
    Route::post('/specialism', [SpecialismController::class, 'update'])
        ->name('specialism.update');
});

// Beveiligde applicatie-routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Search Requests (core functionaliteit)
    Route::resource('search-requests', SearchRequestController::class);

    Route::get(
        'search-requests/{search_request}/recipients',
        [SearchRequestController::class, 'recipients']
    )
        ->name('search-requests.recipients')
        ->middleware('can:view,search_request');

    // Extra domeinacties (policy-first)
    Route::patch(
        'search-requests/{search_request}/assign',
        [SearchRequestController::class, 'assign']
    )
        ->name('search-requests.assign')
        ->middleware('can:assign,search_request');

    Route::patch(
        'search-requests/{search_request}/status',
        [SearchRequestController::class, 'setStatus']
    )
        ->name('search-requests.status')
        ->middleware('can:update,search_request');

    Route::get(
        'search-requests/{search_request}/properties/create',
        [PropertyController::class, 'create']
    )
        ->name('search-requests.properties.create')
        ->middleware('can:offer,search_request');

    Route::post(
        'search-requests/{search_request}/properties',
        [PropertyController::class, 'store']
    )
        ->name('search-requests.properties.store')
        ->middleware('can:offer,search_request');

    Route::post(
        'search-requests/{search_request}/properties/import-funda-business',
        [PropertyController::class, 'importFundaBusiness']
    )
        ->name('search-requests.properties.import-funda-business')
        ->middleware('can:offer,search_request');

    Route::post(
        'search-requests/{search_request}/properties/import-funda-business-html',
        [PropertyController::class, 'importFundaBusinessHtml']
    )
        ->name('search-requests.properties.import-funda-business-html')
        ->middleware('can:offer,search_request');

    Route::post(
        'search-requests/{search_request}/properties/cache-remote-image',
        [PropertyController::class, 'cacheRemoteImage']
    )
        ->name('search-requests.properties.cache-remote-image')
        ->middleware('can:offer,search_request');

    Route::get(
        'search-requests/{search_request}/properties/bag-addresses',
        [PropertyController::class, 'bagAddressSuggestions']
    )
        ->name('search-requests.properties.bag-addresses')
        ->middleware('can:offer,search_request');

    Route::get(
        'search-requests/{search_request}/properties/address-enrichment',
        [PropertyController::class, 'addressEnrichment']
    )
        ->name('search-requests.properties.address-enrichment')
        ->middleware('can:offer,search_request');

    Route::get(
        'search-requests/{search_request}/properties/{property}/edit',
        [PropertyController::class, 'edit']
    )
        ->name('search-requests.properties.edit')
        ->middleware('can:view,property');

    Route::get(
        'search-requests/{search_request}/properties/{property}',
        [PropertyController::class, 'view']
    )
        ->name('search-requests.properties.view')
        ->middleware('can:view,property');

    Route::patch(
        'search-requests/{search_request}/properties/{property}',
        [PropertyController::class, 'update']
    )
        ->name('search-requests.properties.update')
        ->middleware('can:update,property');

    Route::patch(
        'search-requests/{search_request}/properties/{property}/status',
        [PropertyController::class, 'setStatus']
    )
        ->name('search-requests.properties.status')
        ->middleware('can:setStatus,property');

    Route::redirect('/admin/organizations', '/admin/makelaars')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::redirect('/admin/organizations/import', '/admin/makelaars/import')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::redirect('/admin/organizations/create', '/admin/makelaars/create')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::redirect('/admin/organizations/{organization}', '/admin/makelaars/{organization}')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::redirect('/admin/organizations/{organization}/status', '/admin/makelaars/{organization}/status')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::redirect('/admin/organizations/{organization}/users/create', '/admin/makelaars/{organization}/users/create')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/makelaars', [AdminOrganizationController::class, 'index'])
        ->name('admin.organizations.index')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/makelaars/import', [AdminOrganizationController::class, 'importForm'])
        ->name('admin.organizations.import')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::post('/admin/makelaars/import', [AdminOrganizationController::class, 'import'])
        ->name('admin.organizations.import.store')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/makelaars/create', [AdminOrganizationController::class, 'create'])
        ->name('admin.organizations.create')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::post('/admin/makelaars', [AdminOrganizationController::class, 'store'])
        ->name('admin.organizations.store')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/makelaars/{organization}', [AdminOrganizationController::class, 'edit'])
        ->name('admin.organizations.edit')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::patch('/admin/makelaars/{organization}', [AdminOrganizationController::class, 'update'])
        ->name('admin.organizations.update')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::patch('/admin/makelaars/{organization}/status', [AdminOrganizationController::class, 'setStatus'])
        ->name('admin.organizations.status')
        ->middleware('can:manageOrganizations,App\Models\User');

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->name('admin.users.index')
        ->middleware('can:manageUsers,App\Models\User');
    Route::get('/admin/users/create', [AdminUserController::class, 'create'])
        ->name('admin.users.create')
        ->middleware('can:manageUsers,App\Models\User');
    Route::get('/admin/makelaars/{organization}/users/create', [AdminUserController::class, 'create'])
        ->name('admin.organizations.users.create')
        ->middleware('can:manageUsers,App\Models\User');
    Route::post('/admin/makelaars/{organization}/users', [AdminUserController::class, 'store'])
        ->name('admin.organizations.users.store')
        ->middleware('can:manageUsers,App\Models\User');
    Route::get('/admin/users/{user}', [AdminUserController::class, 'edit'])
        ->name('admin.users.edit')
        ->middleware('can:manageUsers,user');
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])
        ->name('admin.users.update')
        ->middleware('can:manageUsers,user');
    Route::patch('/admin/users/{user}/status', [AdminUserController::class, 'setStatus'])
        ->name('admin.users.status')
        ->middleware('can:manageUsers,user');
    Route::patch('/admin/users/{user}/specialism', [AdminUserController::class, 'updateSpecialism'])
        ->name('admin.users.specialism.update')
        ->middleware('can:manageUsers,user');
    Route::post('/admin/users/{user}/password-reset', [AdminUserController::class, 'sendPasswordReset'])
        ->name('admin.users.password-reset')
        ->middleware('can:manageUsers,user');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])
        ->name('admin.users.destroy')
        ->middleware('can:manageUsers,user');

});

// Auth routes (login, register, email verification, etc.)
require __DIR__ . '/auth.php';
