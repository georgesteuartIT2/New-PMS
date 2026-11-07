<?php

namespace App\Http\Controllers;

use App\Models\{ProjectRole, ProjectMembership, ProjectRolePermission, User, Project, Permission};
use App\Services\{Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleDirectoryController
{
    public function index(Request $request, Access $access)
    {
        $search = trim((string) $request->input('q', ''));

        $rolesQuery = ProjectRole::withCount('memberships')
            ->orderBy('is_system', 'desc')
            ->orderBy('name');

        if ($search !== '') {
            $rolesQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $roles = $rolesQuery->get();

        // Selected role
        $selectedId = $request->query('role');
        $selectedRole = null;

        if ($selectedId) {
            $selectedRole = $roles->firstWhere('id', (int) $selectedId) ?? ProjectRole::find($selectedId);
        }

        if (!$selectedRole) {
            $selectedRole = $roles->first() ?? ProjectRole::first();
        }

        // Calculate user and project impact for the selected role
        $affectedUsersCount = 0;
        $affectedProjectsCount = 0;

        if ($selectedRole) {
            if ($selectedRole->slug === 'pm') {
                $pmUserIds = Project::whereNotNull('pm_user_id')->pluck('pm_user_id');
                $membershipUserIds = $selectedRole->memberships()->pluck('user_id');
                $affectedUsersCount = $pmUserIds->merge($membershipUserIds)->unique()->count();
                $affectedProjectsCount = Project::whereNotNull('pm_user_id')->count();
            } else {
                $affectedUsersCount = $selectedRole->memberships()->distinct('user_id')->count('user_id');
                $affectedProjectsCount = $selectedRole->memberships()->distinct('project_id')->count('project_id');
            }
        }

        // Catalog grouped into 4 approved categories
        $groupedCatalog = $access->catalogGrouped();

        // Effective permissions preview context
        $usersList = User::where('account_status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'system_role']);
        $projectsList = Project::orderBy('name')->get(['id', 'code', 'name', 'pm_user_id', 'status']);

        $effectivePreview = null;
        $previewUserId = $request->input('preview_user_id');
        $previewProjectId = $request->input('preview_project_id');

        if ($previewUserId && $previewProjectId) {
            $pUser = User::find($previewUserId);
            $pProj = Project::find($previewProjectId);
            if ($pUser && $pProj) {
                $effectivePreview = $access->getEffectivePermissions($pUser, $pProj);
            }
        }

        return view('roles.index', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'groupedCatalog' => $groupedCatalog,
            'affectedUsersCount' => $affectedUsersCount,
            'affectedProjectsCount' => $affectedProjectsCount,
            'search' => $search,
            'usersList' => $usersList,
            'projectsList' => $projectsList,
            'effectivePreview' => $effectivePreview,
            'previewUserId' => $previewUserId,
            'previewProjectId' => $previewProjectId,
            'permissions' => Access::PERMISSIONS, // legacy fallback for existing views
        ]);
    }

    public function store(Request $request, Access $access)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $slug = Str::slug($data['name'], '_');
        if (!$slug) {
            $slug = 'custom_role_' . time();
        }

        if (ProjectRole::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }

        $perms = array_values(array_unique($data['permissions'] ?? []));

        $role = ProjectRole::create([
            'name' => $data['name'],
            'slug' => $slug,
            'is_system' => false,
            'default_permissions' => $perms,
        ]);

        Audit::record('role.created', $role, [
            'name' => $role->name,
            'slug' => $role->slug,
            'permissions_count' => count($perms),
        ]);

        return redirect()->route('role-directory.index', ['role' => $role->id])
            ->with('success', "Custom role '{$role->name}' created successfully.");
    }

    public function update(Request $request, ProjectRole $role, Access $access)
    {
        $rules = [
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ];

        if (!$role->is_system) {
            $rules['name'] = 'required|string|max:80';
        }

        $data = $request->validate($rules);

        $updateData = [
            'default_permissions' => array_values(array_unique($data['permissions'] ?? [])),
        ];

        if (!$role->is_system && !empty($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        $role->update($updateData);

        Audit::record('role.permissions_updated', $role, [
            'name' => $role->name,
            'is_system' => $role->is_system,
            'permissions_count' => count($updateData['default_permissions']),
        ]);

        return redirect()->route('role-directory.index', ['role' => $role->id])
            ->with('success', "Permissions updated for '{$role->name}'.");
    }

    public function destroy(ProjectRole $role)
    {
        abort_if($role->is_system, 403, 'Built-in system roles cannot be deleted.');

        if ($role->memberships()->exists()) {
            $count = $role->memberships()->count();
            throw ValidationException::withMessages([
                'role' => "This role is currently assigned to {$count} project " . ($count === 1 ? 'member' : 'members') . ". Reassign them before deleting."
            ]);
        }

        ProjectRolePermission::where('project_role_id', $role->id)->delete();

        $roleName = $role->name;
        $role->delete();

        Audit::record('role.deleted', null, ['name' => $roleName]);

        return redirect()->route('role-directory.index')
            ->with('success', "Custom role '{$roleName}' deleted successfully.");
    }

    public function previewEffective(Request $request, Access $access)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'project_id' => 'required|exists:projects,id',
        ]);

        $user = User::findOrFail($data['user_id']);
        $project = Project::findOrFail($data['project_id']);

        $effective = $access->getEffectivePermissions($user, $project);

        if ($request->wantsJson()) {
            return response()->json($effective);
        }

        return redirect()->route('role-directory.index', [
            'role' => $request->input('current_role_id'),
            'preview_user_id' => $user->id,
            'preview_project_id' => $project->id,
        ]);
    }
}
