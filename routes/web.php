<?php

use App\Http\Controllers\SearchRequestController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
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

// Dashboard (breeze default)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
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
});

// Beveiligde applicatie-routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Search Requests (core functionaliteit)
    Route::resource('search-requests', SearchRequestController::class)
        ->except(['destroy']); // destroy later indien nodig

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
});

// Auth routes (login, register, email verification, etc.)
require __DIR__ . '/auth.php';
