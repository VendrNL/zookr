<?php

use App\Http\Controllers\SearchRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecialismController;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Web routes voor de Zookr applicatie
*/

// Root: slim redirecten op basis van login-status
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('search-requests.index')
        : redirect()->route('login');
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

// Dashboard (toon Search Requests overzicht)
Route::get('/dashboard', [SearchRequestController::class, 'index'])
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

    Route::get('/organization', [OrganizationController::class, 'edit'])
        ->name('organization.edit');
    Route::post('/organization', [OrganizationController::class, 'update'])
        ->name('organization.update');
    Route::get('/organization/users/create', [OrganizationController::class, 'createUser'])
        ->name('organization.users.create');
    Route::post('/organization/users', [OrganizationController::class, 'storeUser'])
        ->name('organization.users.store');
    Route::get('/organization/users/{user}', [OrganizationController::class, 'editUser'])
        ->name('organization.users.edit');
    Route::patch('/organization/users/{user}', [OrganizationController::class, 'updateUser'])
        ->name('organization.users.update');
    Route::patch('/organization/users/{user}/status', [OrganizationController::class, 'setUserStatus'])
        ->name('organization.users.status');

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

    Route::get('/admin/organizations', [AdminOrganizationController::class, 'index'])
        ->name('admin.organizations.index')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/organizations/import', [AdminOrganizationController::class, 'importForm'])
        ->name('admin.organizations.import')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::post('/admin/organizations/import', [AdminOrganizationController::class, 'import'])
        ->name('admin.organizations.import.store')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/organizations/create', [AdminOrganizationController::class, 'create'])
        ->name('admin.organizations.create')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::post('/admin/organizations', [AdminOrganizationController::class, 'store'])
        ->name('admin.organizations.store')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::get('/admin/organizations/{organization}', [AdminOrganizationController::class, 'edit'])
        ->name('admin.organizations.edit')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::patch('/admin/organizations/{organization}', [AdminOrganizationController::class, 'update'])
        ->name('admin.organizations.update')
        ->middleware('can:manageOrganizations,App\Models\User');
    Route::patch('/admin/organizations/{organization}/status', [AdminOrganizationController::class, 'setStatus'])
        ->name('admin.organizations.status')
        ->middleware('can:manageOrganizations,App\Models\User');

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->name('admin.users.index')
        ->middleware('can:manageUsers,App\Models\User');
    Route::get('/admin/organizations/{organization}/users/create', [AdminUserController::class, 'create'])
        ->name('admin.organizations.users.create')
        ->middleware('can:manageUsers,App\Models\User');
    Route::post('/admin/organizations/{organization}/users', [AdminUserController::class, 'store'])
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
});

// Auth routes (login, register, email verification, etc.)
require __DIR__ . '/auth.php';
