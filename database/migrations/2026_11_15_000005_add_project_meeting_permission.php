<?php

use App\Models\{Permission, ProjectRole};
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::updateOrCreate(['key' => 'project.meetings_manage'], [
            'category' => 'project',
            'name' => 'Manage Project Meetings',
            'description' => 'Check project member availability and schedule, update, or cancel project meetings.',
            'sort_order' => (Permission::max('sort_order') ?? 0) + 1,
        ]);

        foreach (ProjectRole::whereIn('slug', ['pm', 'po'])->get() as $role) {
            $permissions = collect($role->default_permissions ?? [])->push('project.meetings_manage')->unique()->values()->all();
            $role->update(['default_permissions' => $permissions]);
        }
    }

    public function down(): void
    {
        Permission::where('key', 'project.meetings_manage')->delete();
        foreach (ProjectRole::whereIn('slug', ['pm', 'po'])->get() as $role) {
            $role->update(['default_permissions' => collect($role->default_permissions ?? [])->reject(fn ($permission) => $permission === 'project.meetings_manage')->values()->all()]);
        }
    }
};
