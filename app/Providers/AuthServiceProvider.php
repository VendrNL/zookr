<?php

namespace App\Providers;

use App\Models\SearchRequest;
use App\Policies\SearchRequestPolicy;
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
    ];

    public function boot(): void
    {
        //
    }
}
