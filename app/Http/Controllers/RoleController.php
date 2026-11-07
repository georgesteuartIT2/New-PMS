<?php

namespace App\Http\Controllers;

use App\Models\{Project, ProjectRole, ProjectRolePermission};
use App\Services\{Access, Audit};
use Illuminate\Http\Request;

class RoleController
{
    public function index(Project $project, Access $access)
    {
        return view('projects.permissions', [
            'project' => $project,
            'roles' => ProjectRole::orderBy('is_system', 'desc')->orderBy('name')->get(),
            'available' => Access::PERMISSIONS,
            'catalog' => $access->catalogGrouped(),
            'overrides' => ProjectRolePermission::where('project_id', $project->id)->get()->groupBy('project_role_id'),
        ]);
    }

    public function update(Request $request, Project $project, Access $access)
    {
        $data = $request->validate([
            'permissions' => 'nullable|array',
        ]);

        $roles = ProjectRole::all();

        foreach ($roles as $role) {
            // Check all permissions configured
            foreach (array_keys(Access::PERMISSIONS) as $permission) {
                $checked = (bool) data_get($data, "permissions.{$role->id}.{$permission}", false);
                ProjectRolePermission::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'project_role_id' => $role->id,
                        'permission' => $permission,
                    ],
                    [
                        'allowed' => $checked,
                    ]
                );
            }
        }

        Audit::record('project.permissions_updated', $project, [
            'project_code' => $project->code,
        ]);

        return back()->with('success', 'Project-specific permissions saved.');
    }

    public function createCustom(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => 'required|alpha_dash|max:80|unique:project_roles,slug',
        ]);

        ProjectRole::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_system' => false,
            'default_permissions' => ['project.view', 'view_project'],
        ]);

        return back()->with('success', 'Custom role created.');
    }
}
