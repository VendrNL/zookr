<?php

namespace App\Policies;

use App\Models\SearchRequest;
use App\Models\User;

class SearchRequestPolicy
{
    public function viewAny(User $user): bool
    {
        // Iedereen mag naar de index (we filteren in de query al voor niet-admins)
        return true;
    }

    public function view(User $user, SearchRequest $searchRequest): bool
    {
        return $user->is_admin
            || $searchRequest->created_by === $user->id
            || $searchRequest->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        // iedereen met account mag een aanvraag maken
        return true;
    }

    public function update(User $user, SearchRequest $searchRequest): bool
    {
        // admin of betrokkenen mogen updaten
        return $user->is_admin
            || $searchRequest->created_by === $user->id
            || $searchRequest->assigned_to === $user->id;
    }

    public function delete(User $user, SearchRequest $searchRequest): bool
    {
        // strakker: alleen admin of de maker
        return $user->is_admin || $searchRequest->created_by === $user->id;
    }
}
