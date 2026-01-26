<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        return $this->sameOrganization($user, $property);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->sameOrganization($user, $property);
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
}
