<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\SalesProspect;
use App\Models\User;

class SalesProspectPolicy
{
    public function view(User $user, SalesProspect $prospect, Membership $actor): bool
    {
        return $this->isVisible($prospect, $actor);
    }

    public function update(User $user, SalesProspect $prospect, Membership $actor): bool
    {
        return $this->isVisible($prospect, $actor);
    }

    public function assign(User $user, SalesProspect $prospect, Membership $actor): bool
    {
        if (! in_array($actor->role, ['supervisor', 'manager', 'deza_admin'], true)) {
            return false;
        }

        return $this->isVisible($prospect, $actor);
    }

    private function isVisible(SalesProspect $prospect, Membership $actor): bool
    {
        $visibleIds = Membership::visibleIdsFor($actor);

        return $visibleIds === null || in_array($prospect->owner_membership_id, $visibleIds, true);
    }
}