<?php

namespace App\Services;

use App\Models\{User, Project, Task, ProjectRole, ProjectMembership, ProjectRolePermission, UserPermission, Permission};
use Illuminate\Support\Collection;

class Access {
    public const CATEGORIES = [
        'company' => [
            'title' => 'Company-wide Permissions',
            'description' => 'System-wide authorities affecting cross-subsidiary governance and administration.'
        ],
        'project' => [
            'title' => 'Project Permissions',
            'description' => 'Project-level governance, schedule, closure, and member management.'
        ],
        'task' => [
            'title' => 'Task Permissions',
            'description' => 'Task creation, progress tracking, collaboration, dependencies, and deliverables.'
        ],
        'approval' => [
            'title' => 'Approval Permissions',
            'description' => 'Scope adjustment reviews, change requests, and delay resolution authority.'
        ],
    ];

    /**
     * Standard Enterprise Permission Catalog (34 Canonical Keys)
     */
    public const CATALOG = [
        // Company-wide Permissions
        'projects.create' => ['category' => 'company', 'name' => 'Create Projects', 'description' => 'Initiate new corporate projects and launch PM assignment.'],
        'projects.view_all' => ['category' => 'company', 'name' => 'View All Company Projects', 'description' => 'View all projects across all subsidiaries and departments.'],
        'users.manage' => ['category' => 'company', 'name' => 'Manage Users', 'description' => 'Create, update, activate and deactivate enterprise user accounts.'],
        'subsidiaries.manage' => ['category' => 'company', 'name' => 'Manage Subsidiaries', 'description' => 'Create and configure company subsidiaries and organizational units.'],
        'templates.manage' => ['category' => 'company', 'name' => 'Manage Project Templates', 'description' => 'Create, modify and manage reusable corporate project templates.'],
        'roles.manage' => ['category' => 'company', 'name' => 'Manage Roles and Permissions', 'description' => 'Configure system roles, custom project roles, and permission policies.'],
        'reports.portfolio' => ['category' => 'company', 'name' => 'View Portfolio Reports', 'description' => 'Access organization-wide portfolio dashboards and analytical reports.'],

        // Project Permissions
        'project.view' => ['category' => 'project', 'name' => 'View Project', 'description' => 'View project overview, milestones, team members, and basic information.'],
        'project.edit' => ['category' => 'project', 'name' => 'Edit Project', 'description' => 'Edit project details, description, start and end dates.'],
        'project.members_manage' => ['category' => 'project', 'name' => 'Manage Project Members', 'description' => 'Add, change roles, and remove team members from the project.'],
        'project.gantt_view' => ['category' => 'project', 'name' => 'View Project Gantt', 'description' => 'Access the interactive project Gantt chart timeline.'],
        'project.schedule_manage' => ['category' => 'project', 'name' => 'Manage Project Schedule', 'description' => 'Capture schedule baselines and adjust timeline schedules.'],
        'project.reports_view' => ['category' => 'project', 'name' => 'View Project Reports', 'description' => 'Export task lists to Excel and access project performance metrics.'],
        'project.closure_request' => ['category' => 'project', 'name' => 'Request Project Closure', 'description' => 'Submit a project for formal PMO closure review upon completion.'],
        'project.closure_approve' => ['category' => 'project', 'name' => 'Approve Project Closure', 'description' => 'Final authorization to formally close and archive the project.'],

        // Task Permissions
        'tasks.view_assigned' => ['category' => 'task', 'name' => 'View Assigned Tasks', 'description' => 'View tasks specifically assigned to the user.'],
        'tasks.view_all' => ['category' => 'task', 'name' => 'View All Tasks in Assigned Project', 'description' => 'View all tasks across the entire project plan regardless of assignment.'],
        'tasks.create' => ['category' => 'task', 'name' => 'Create Tasks', 'description' => 'Add new tasks, subtasks, and milestones to the project plan.'],
        'tasks.edit_assigned' => ['category' => 'task', 'name' => 'Edit Assigned Tasks', 'description' => 'Update progress and status on tasks assigned to the user.'],
        'tasks.edit_all' => ['category' => 'task', 'name' => 'Edit All Project Tasks', 'description' => 'Edit dates, priorities, titles, and descriptions of any project task.'],
        'tasks.assign' => ['category' => 'task', 'name' => 'Assign Tasks', 'description' => 'Assign and reassign project members to tasks.'],
        'tasks.delete' => ['category' => 'task', 'name' => 'Delete Tasks', 'description' => 'Delete tasks and subtasks from the project plan.'],
        'tasks.update_status' => ['category' => 'task', 'name' => 'Update Task Status', 'description' => 'Change task status (To Do, In Progress, On Hold, Completed).'],
        'tasks.update_progress' => ['category' => 'task', 'name' => 'Update Task Progress', 'description' => 'Update completion percentage (0% to 100%) on tasks.'],
        'tasks.upload_attachments' => ['category' => 'task', 'name' => 'Upload Task Attachments', 'description' => 'Upload specification documents, files, and deliverables to tasks.'],
        'tasks.download_attachments' => ['category' => 'task', 'name' => 'Download Task Attachments', 'description' => 'Download and view documents and files attached to tasks.'],
        'tasks.add_comments' => ['category' => 'task', 'name' => 'Add Task Comments', 'description' => 'Post comments, updates, and feedback on task threads.'],
        'tasks.report_delays' => ['category' => 'task', 'name' => 'Report Task Delays', 'description' => 'Log delays, blockers, responsible parties, and revised dates.'],
        'tasks.manage_dependencies' => ['category' => 'task', 'name' => 'Manage Task Dependencies', 'description' => 'Define finish-to-start predecessor relationships between tasks.'],
        'tasks.view_critical_path' => ['category' => 'task', 'name' => 'View Critical Path', 'description' => 'Inspect the calculated zero-float critical path through project tasks.'],

        // Approval Permissions
        'approvals.review_changes' => ['category' => 'approval', 'name' => 'Review Change Requests', 'description' => 'Examine proposed scope, schedule, or budget adjustments.'],
        'approvals.approve_changes' => ['category' => 'approval', 'name' => 'Approve Change Requests', 'description' => 'Formally approve or reject project change requests.'],
        'approvals.review_delays' => ['category' => 'approval', 'name' => 'Review Delay Reports', 'description' => 'Review reported task delays and blocker impacts.'],
        'approvals.manage_corrective_actions' => ['category' => 'approval', 'name' => 'Manage Corrective Actions', 'description' => 'Assign corrective action owners and mark delays resolved.'],
    ];

    /**
     * Bidirectional legacy aliases map ensuring 100% backward compatibility
     */
    public const ALIASES = [
        'view_project' => 'project.view',
        'edit_project' => 'project.edit',
        'view_tasks' => 'tasks.view_all',
        'view_gantt' => 'project.gantt_view',
        'manage_tasks' => 'tasks.create',
        'assign_tasks' => 'tasks.assign',
        'manage_team' => 'project.members_manage',
        'upload_files' => 'tasks.upload_attachments',
        'view_assigned_tasks' => 'tasks.view_assigned',
        'update_own_tasks' => 'tasks.edit_assigned',
        'upload_own_files' => 'tasks.upload_attachments',
    ];

    /**
     * Legacy simple array for backward compatibility
     */
    public const PERMISSIONS = [
        'project.view' => 'View project',
        'tasks.view_all' => 'View project tasks',
        'project.gantt_view' => 'View Gantt',
        'project.edit' => 'Edit project',
        'tasks.create' => 'Create and edit tasks',
        'tasks.assign' => 'Assign tasks',
        'project.members_manage' => 'Manage project members',
        'tasks.upload_attachments' => 'Upload task files',
    ];

    /**
     * Normalize permission to canonical dot-notation key
     */
    public function normalize(string $permission): string {
        return self::ALIASES[$permission] ?? $permission;
    }

    /**
     * Get all equivalent keys (canonical key and legacy aliases)
     */
    public function matchingKeys(string $permission): array {
        $canonical = $this->normalize($permission);
        $keys = [$canonical];
        foreach (self::ALIASES as $legacy => $target) {
            if ($target === $canonical) {
                $keys[] = $legacy;
            }
        }
        if ($permission !== $canonical && !in_array($permission, $keys, true)) {
            $keys[] = $permission;
        }
        return array_values(array_unique($keys));
    }

    /**
     * Get all catalog permissions grouped by category
     */
    public function catalogGrouped(): array {
        $grouped = [];
        foreach (self::CATEGORIES as $catKey => $catInfo) {
            $grouped[$catKey] = [
                'meta' => $catInfo,
                'permissions' => []
            ];
        }

        foreach (self::CATALOG as $key => $info) {
            $cat = $info['category'];
            if (isset($grouped[$cat])) {
                $grouped[$cat]['permissions'][$key] = $info;
            }
        }

        return $grouped;
    }

    /**
     * Check if user has a company-wide permission (e.g. 'projects.create', 'reports.portfolio')
     */
    public function hasCompanyPermission(User $user, string $permission): bool {
        if (!$user->isActive()) return false;
        if ($user->isPmo()) return true;

        $canonical = $this->normalize($permission);
        $matching = $this->matchingKeys($permission);

        // 1. Check direct user-level grant
        $direct = UserPermission::where('user_id', $user->id)
            ->whereIn('permission', $matching)
            ->first();

        if ($direct !== null) {
            return (bool) $direct->allowed;
        }

        // 2. Check roles held by the user:
        // A) If assigned as Project Manager on active or pending projects, or pm role policy has it
        $isPmUser = $user->pmProjects()->exists();
        if ($isPmUser) {
            $pmRole = ProjectRole::where('slug', 'pm')->first();
            if ($pmRole && !empty($pmRole->default_permissions)) {
                foreach ($matching as $key) {
                    if (in_array($key, $pmRole->default_permissions, true)) {
                        return true;
                    }
                }
            }
        }

        // B) Check project memberships held by the user
        $roleIds = $user->memberships()->pluck('project_role_id')->unique();
        if ($roleIds->isNotEmpty()) {
            $roles = ProjectRole::whereIn('id', $roleIds)->get();
            foreach ($roles as $role) {
                if (empty($role->default_permissions)) continue;
                foreach ($matching as $key) {
                    if (in_array($key, $role->default_permissions, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all memberships for a user in a specific project.
     */
    public function memberships(User $user, Project $project): Collection {
        return ProjectMembership::with('role')
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->get();
    }

    /**
     * Get primary role membership for a user in a project.
     */
    public function role(User $user, Project $project): ?ProjectMembership {
        return $this->memberships($user, $project)->first();
    }

    /**
     * Get human-readable role labels for the user in a project (e.g. "Project Manager", "Team Member").
     */
    public function roleLabels(User $user, Project $project): array {
        $labels = [];
        if ($project->pm_user_id === $user->id) {
            $labels[] = 'Project Manager';
        }
        foreach ($this->memberships($user, $project) as $m) {
            if ($m->role && !in_array($m->role->name, $labels, true)) {
                $labels[] = $m->role->name;
            }
        }
        if (empty($labels) && $user->isPmo()) {
            $labels[] = 'PMO Admin';
        }
        return $labels ?: ['Member'];
    }

    /**
     * Check project-level permission.
     */
    public function project(User $user, Project $project, string $permission = 'project.view'): bool {
        if (!$user->isActive()) return false;
        if ($user->isPmo()) return true;

        // Draft projects are strictly visible ONLY to PMO Admin
        if ($project->status === 'draft') {
            return false;
        }

        $canonical = $this->normalize($permission);
        $matching = $this->matchingKeys($permission);

        // Project lifecycle checks
        $isPm = ($project->pm_user_id === $user->id);

        if ($isPm) {
            if (in_array($project->status, ['pending', 'declined'], true)) {
                // Invited PM can review plan, tasks, and Gantt before responding
                return in_array($canonical, ['project.view', 'tasks.view_all', 'project.gantt_view', 'project.reports_view'], true);
            }

            if ($project->status === 'archived') {
                return in_array($canonical, ['project.view', 'tasks.view_all', 'project.gantt_view', 'project.reports_view'], true);
            }

            // Check if there is a project-specific restriction for PM role
            $pmRole = ProjectRole::where('slug', 'pm')->first();
            if ($pmRole) {
                $override = ProjectRolePermission::where('project_id', $project->id)
                    ->where('project_role_id', $pmRole->id)
                    ->whereIn('permission', $matching)
                    ->first();

                if ($override !== null) {
                    return (bool) $override->allowed;
                }

                // Check PM role default permissions
                if (!empty($pmRole->default_permissions)) {
                    foreach ($matching as $k) {
                        if (in_array($k, $pmRole->default_permissions, true)) {
                            return true;
                        }
                    }
                }
            }

            // Default PM capabilities within assigned project
            return true;
        }

        // For non-PM members: must be an active project lifecycle
        if (!in_array($project->status, ['active', 'on_hold', 'completed', 'archived'], true)) {
            return false;
        }

        $memberships = $this->memberships($user, $project);
        if ($memberships->isEmpty()) {
            return false;
        }

        // Evaluate all memberships held by user in this project
        foreach ($memberships as $membership) {
            $role = $membership->role;
            if (!$role) continue;

            // 1. Project-specific role override configured by PMO
            $override = ProjectRolePermission::where('project_id', $project->id)
                ->where('project_role_id', $membership->project_role_id)
                ->whereIn('permission', $matching)
                ->first();

            if ($override !== null) {
                if ($override->allowed) {
                    if ($project->status === 'archived' && !in_array($canonical, ['project.view', 'tasks.view_all', 'project.gantt_view', 'project.reports_view'], true)) {
                        continue;
                    }
                    return true;
                }
                // Explicit project restriction for this role
                continue;
            }

            // 2. Default role permissions:
            // Team Member role alone has restricted project-wide access (assigned tasks only)
            if ($role->slug === 'team') {
                continue;
            }

            // Check role default permissions
            if (!empty($role->default_permissions)) {
                foreach ($matching as $k) {
                    if (in_array($k, $role->default_permissions, true)) {
                        if ($project->status === 'archived' && !in_array($canonical, ['project.view', 'tasks.view_all', 'project.gantt_view', 'project.reports_view'], true)) {
                            continue;
                        }
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check task-level permission.
     */
    public function task(User $user, Task $task, string $permission = 'view'): bool {
        $project = $task->project;
        if (!$project || !$user->isActive()) return false;

        // PMO Admin has global access
        if ($user->isPmo()) {
            return $project->status !== 'archived' || in_array($permission, ['view', 'download'], true);
        }

        // Draft projects are strictly visible ONLY to PMO Admin
        if ($project->status === 'draft') {
            return false;
        }

        // Project Manager of this project
        if ($project->pm_user_id === $user->id) {
            if (!in_array($project->status, ['active', 'on_hold', 'completed', 'archived'], true)) {
                return in_array($permission, ['view', 'download'], true) && in_array($project->status, ['pending', 'declined'], true);
            }
            return $project->status !== 'archived' || in_array($permission, ['view', 'download'], true);
        }

        if (!in_array($project->status, ['active', 'on_hold', 'completed'], true)) {
            return false;
        }

        // Full task management permissions on this project
        if ($this->canManageTask($user, $project)) {
            return true;
        }

        // Project-wide task viewing permissions (e.g. Sponsor, Owner, Steering, or Team member with tasks.view_all override)
        if ($this->project($user, $project, 'tasks.view_all') && in_array($permission, ['view', 'download'], true)) {
            return true;
        }

        // Check if user is an assignee of this task
        $isAssigned = $task->assignees()->where('users.id', $user->id)->exists();
        if ($isAssigned) {
            if (in_array($permission, ['view', 'download'], true)) {
                return true;
            }
            if (in_array($permission, ['update', 'upload', 'comment', 'delay'], true)) {
                return $project->status === 'active';
            }
        }

        return false;
    }

    public function canManageTask(User $user, Project $project): bool {
        return $this->project($user, $project, 'tasks.create') ||
               $this->project($user, $project, 'tasks.edit_all') ||
               $this->project($user, $project, 'manage_tasks');
    }

    /**
     * Calculate effective permissions breakdown for a selected user and project
     */
    public function getEffectivePermissions(User $user, Project $project): array {
        $roles = $this->roleLabels($user, $project);
        $isPmo = $user->isPmo();
        $isPm = ($project->pm_user_id === $user->id);
        $memberships = $this->memberships($user, $project);

        $results = [];

        foreach (self::CATALOG as $key => $info) {
            $matching = $this->matchingKeys($key);
            $allowed = false;
            $source = 'Denied';

            if ($isPmo) {
                $allowed = true;
                $source = 'PMO Administrator (Full Authority)';
            } else {
                // Check direct user permission
                $direct = UserPermission::where('user_id', $user->id)->whereIn('permission', $matching)->first();
                if ($direct !== null) {
                    $allowed = (bool) $direct->allowed;
                    $source = $allowed ? 'User Direct Grant' : 'User Direct Restriction';
                } elseif ($info['category'] === 'company') {
                    $allowed = $this->hasCompanyPermission($user, $key);
                    $source = $allowed ? 'Role Default Policy (Company-wide)' : 'Not Granted';
                } else {
                    // Check project-level permissions
                    if ($isPm) {
                        $pmRole = ProjectRole::where('slug', 'pm')->first();
                        $override = $pmRole ? ProjectRolePermission::where('project_id', $project->id)
                            ->where('project_role_id', $pmRole->id)
                            ->whereIn('permission', $matching)
                            ->first() : null;

                        if ($override !== null) {
                            $allowed = (bool) $override->allowed;
                            $source = $allowed ? 'Project Override: Granted for PM' : 'Project Override: Denied for PM';
                        } else {
                            $allowed = true;
                            $source = 'Role Default (Project Manager)';
                        }
                    } else {
                        // Check memberships and overrides
                        foreach ($memberships as $m) {
                            $r = $m->role;
                            if (!$r) continue;

                            $override = ProjectRolePermission::where('project_id', $project->id)
                                ->where('project_role_id', $m->project_role_id)
                                ->whereIn('permission', $matching)
                                ->first();

                            if ($override !== null) {
                                if ($override->allowed) {
                                    $allowed = true;
                                    $source = "Project Override: Granted for {$r->name}";
                                    break;
                                } else {
                                    $source = "Project Override: Denied for {$r->name}";
                                }
                            } elseif ($r->slug !== 'team' && !empty($r->default_permissions)) {
                                foreach ($matching as $mk) {
                                    if (in_array($mk, $r->default_permissions, true)) {
                                        $allowed = true;
                                        $source = "Role Default: {$r->name}";
                                        break 2;
                                    }
                                }
                            }
                        }

                        if (!$allowed && (empty($source) || $source === 'Denied')) {
                            $source = $memberships->isEmpty() ? 'No Project Membership' : 'Not Granted in Role Policy';
                        }
                    }
                }
            }

            $results[$info['category']][] = [
                'key' => $key,
                'name' => $info['name'],
                'description' => $info['description'],
                'allowed' => $allowed,
                'source' => $source,
            ];
        }

        return [
            'user' => $user,
            'project' => $project,
            'roles' => $roles,
            'permissions' => $results,
        ];
    }
}
