<?php

namespace App\Http\Controllers;

use App\Models\{Project, ProjectRole, ProjectRolePermission};
use App\Services\{Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController
{
    public function index(Project $project, Access $access)
    {
        $available = collect(Access::CATALOG)
            ->reject(fn (array $definition) => $definition['category'] === 'company')
            ->all();

        return view('projects.permissions', [
            'project' => $project,
            'roles' => ProjectRole::orderBy('is_system', 'desc')->orderBy('name')->get(),
            'available' => $available,
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

        DB::transaction(function () use ($roles, $project, $data) {
            // Replace the complete project policy so legacy aliases cannot
            // silently override a newly unchecked canonical permission.
            ProjectRolePermission::where('project_id', $project->id)->delete();

            foreach ($roles as $role) {
            // Check all permissions configured
            $projectPermissions = collect(Access::CATALOG)
                ->reject(fn (array $definition) => $definition['category'] === 'company')
                ->keys();

            foreach ($projectPermissions as $permission) {
                $checked = (bool) ($data['permissions'][$role->id][$permission] ?? false);
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
        });

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
