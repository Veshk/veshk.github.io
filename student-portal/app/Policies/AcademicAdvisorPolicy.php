<?php

namespace App\Policies;

use App\Models\AcademicAdvisor;
use App\Models\User;

class AcademicAdvisorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, AcademicAdvisor $advisor): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isSenior();
    }

    public function update(User $user, AcademicAdvisor $advisor): bool
    {
        return $user->isSenior();
    }

    public function delete(User $user, AcademicAdvisor $advisor): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
