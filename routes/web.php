<?php

use App\Http\Controllers\SearchRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecialismController;

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

    Route::get('/specialism', [SpecialismController::class, 'edit'])
        ->name('specialism.edit');
    Route::post('/specialism', [SpecialismController::class, 'update'])
        ->name('specialism.update');
});

// Beveiligde applicatie-routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Search Requests (core functionaliteit)
    Route::resource('search-requests', SearchRequestController::class);

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
    Route::get('/admin/organizations/{user}', [AdminOrganizationController::class, 'edit'])
        ->name('admin.organizations.edit')
        ->middleware('can:manageOrganizations,user');
    Route::patch('/admin/organizations/{user}', [AdminOrganizationController::class, 'update'])
        ->name('admin.organizations.update')
        ->middleware('can:manageOrganizations,user');

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->name('admin.users.index')
        ->middleware('can:manageUsers,App\Models\User');
    Route::get('/admin/users/{user}', [AdminUserController::class, 'edit'])
        ->name('admin.users.edit')
        ->middleware('can:manageUsers,user');
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])
        ->name('admin.users.update')
        ->middleware('can:manageUsers,user');
    Route::patch('/admin/users/{user}/specialism', [AdminUserController::class, 'updateSpecialism'])
        ->name('admin.users.specialism.update')
        ->middleware('can:manageUsers,user');
});

// Auth routes (login, register, email verification, etc.)
require __DIR__ . '/auth.php';
