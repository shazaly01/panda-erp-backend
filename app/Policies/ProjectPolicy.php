<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('project.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('project.view');
    }

    public function create(User $user): bool
    {
        return $user->can('project.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('project.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('project.delete');
    }
}
