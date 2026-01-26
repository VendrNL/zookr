<?php

namespace App\Providers;

use App\Models\SearchRequest;
use App\Models\User;
use App\Models\Property;
use App\Policies\PropertyPolicy;
use App\Policies\SearchRequestPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        SearchRequest::class => SearchRequestPolicy::class,
        Property::class => PropertyPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
