<?php

namespace App\Policies;

use App\Models\SearchRequest;
use App\Models\User;

class SearchRequestPolicy
{
    public function viewAny(User $user): bool
    {
        // Iedereen mag naar de index
        return true;
    }

    public function view(User $user, SearchRequest $searchRequest): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // iedereen met account mag een aanvraag maken
        return true;
    }

    public function update(User $user, SearchRequest $searchRequest): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $this->sameOrganization($user, $searchRequest);
    }

    public function delete(User $user, SearchRequest $searchRequest): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $this->sameOrganization($user, $searchRequest);
    }

    public function offer(User $user, SearchRequest $searchRequest): bool
    {
        return $this->differentOrganization($user, $searchRequest);
    }

    private function sameOrganization(User $user, SearchRequest $searchRequest): bool
    {
        $userOrgId = $user->organization_id;
        $requestOrgId = $searchRequest->organization_id;

        if (! $userOrgId || ! $requestOrgId) {
            return false;
        }

        return (int) $userOrgId === (int) $requestOrgId;
    }

    private function differentOrganization(User $user, SearchRequest $searchRequest): bool
    {
        $userOrgId = $user->organization_id;
        $requestOrgId = $searchRequest->organization_id;

        if (! $userOrgId || ! $requestOrgId) {
            return false;
        }

        return (int) $userOrgId !== (int) $requestOrgId;
    }
}
