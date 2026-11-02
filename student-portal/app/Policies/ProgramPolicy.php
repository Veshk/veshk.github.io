<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Program $program): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isSenior();
    }

    public function update(User $user, Program $program): bool
    {
        return $user->isSenior();
    }

    public function delete(User $user, Program $program): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
