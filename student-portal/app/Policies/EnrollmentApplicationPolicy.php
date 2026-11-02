<?php

namespace App\Policies;

use App\Models\EnrollmentApplication;
use App\Models\User;

class EnrollmentApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, EnrollmentApplication $application): bool
    {
        return $user->isStaff() && ($application->study_group_id !== null || $user->reviews_unassigned);
    }

    public function review(User $user, EnrollmentApplication $application): bool
    {
        if (! $user->isStaff()) {
            return false;
        }
        if (! $application->study_group_id) {
            return $user->reviews_unassigned;
        }

        return $user->isSenior() || $application->group->administrator_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EnrollmentApplication $application): bool
    {
        return false;
    }

    public function delete(User $user, EnrollmentApplication $application): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
