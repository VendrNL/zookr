<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        if ($this->sameOrganization($user, $property)) {
            return true;
        }

        $searchRequest = $property->searchRequest;
        if (! $searchRequest) {
            return false;
        }

        return $this->sameOrganizationForRequest($user, $searchRequest);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->sameOrganization($user, $property);
    }

    public function setStatus(User $user, Property $property): bool
    {
        if ($this->sameOrganization($user, $property)) {
            return true;
        }

        $searchRequest = $property->searchRequest;
        if (! $searchRequest) {
            return false;
        }

        return $this->sameOrganizationForRequest($user, $searchRequest);
    }

    private function sameOrganization(User $user, Property $property): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $userOrgId = $user->organization_id;
        $propertyOrgId = $property->organization_id;

        if (! $userOrgId || ! $propertyOrgId) {
            return false;
        }

        return (int) $userOrgId === (int) $propertyOrgId;
    }

    private function sameOrganizationForRequest(User $user, \App\Models\SearchRequest $searchRequest): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $userOrgId = $user->organization_id;
        $requestOrgId = $searchRequest->organization_id;

        if (! $userOrgId || ! $requestOrgId) {
            return false;
        }

        return (int) $userOrgId === (int) $requestOrgId;
    }
}
