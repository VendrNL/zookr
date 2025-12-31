<?php

namespace App\Providers;

use App\Models\SearchRequest;
use App\Policies\SearchRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        SearchRequest::class => SearchRequestPolicy::class,
    ];
}
