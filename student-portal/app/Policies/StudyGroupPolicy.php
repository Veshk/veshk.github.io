<?php

namespace App\Policies;

use App\Models\StudyGroup;
use App\Models\User;

class StudyGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, StudyGroup $group): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isSenior();
    }

    public function update(User $user, StudyGroup $group): bool
    {
        return $user->isSenior() || ($user->isStaff() && $group->administrator_id === $user->id);
    }

    public function delete(User $user, StudyGroup $group): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
