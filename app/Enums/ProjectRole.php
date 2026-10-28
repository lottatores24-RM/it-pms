<?php

namespace App\Enums;

/**
 * A user's role inside a single project (project_members.role_in_project).
 * Each value matches a Spatie role name, so project-level authorization
 * reuses the permissions an Administrator configures for that role.
 */
enum ProjectRole: string
{
    case ProjectManager = 'Project Manager';
    case TeamMember = 'Team Member';
    case Viewer = 'Viewer';
}
