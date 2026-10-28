<?php

namespace App\Policies;

use App\Enums\RiskStatus;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;

/**
 * Risk register rules (FR-013, FR-014). Team Members and Viewers are
 * read-only; closed risks and closed projects cannot be edited.
 */
class RiskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'risk.viewAny');
    }

    public function view(User $user, Risk $risk): bool
    {
        return $user->hasProjectPermission($risk->project, 'risk.view');
    }

    public function create(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'risk.create');
    }

    public function update(User $user, Risk $risk): bool
    {
        return ! $risk->project->isClosed()
            && $risk->status !== RiskStatus::Closed
            && $user->hasProjectPermission($risk->project, 'risk.update');
    }

    /**
     * Closing needs risk.close; mitigating and reopening need risk.update.
     */
    public function changeStatus(User $user, Risk $risk, RiskStatus $to): bool
    {
        if ($risk->project->isClosed()) {
            return false;
        }

        return $user->hasProjectPermission($risk->project, $to === RiskStatus::Closed ? 'risk.close' : 'risk.update');
    }

    public function delete(User $user, Risk $risk): bool
    {
        return ! $risk->project->isClosed() && $user->hasProjectPermission($risk->project, 'risk.delete');
    }
}
