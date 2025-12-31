<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchRequestController;

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
