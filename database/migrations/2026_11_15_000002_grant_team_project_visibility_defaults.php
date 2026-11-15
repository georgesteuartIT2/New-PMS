<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('project_roles')->where('slug', 'team')->first();
        if (!$role) return;

        $permissions = json_decode($role->default_permissions ?: '[]', true) ?: [];
        $permissions = array_values(array_unique(array_merge($permissions, [
            'project.view',
            'project.gantt_view',
            'view_project',
            'view_gantt',
        ])));

        DB::table('project_roles')->where('id', $role->id)->update([
            'default_permissions' => json_encode($permissions),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $role = DB::table('project_roles')->where('slug', 'team')->first();
        if (!$role) return;

        $remove = ['project.view', 'project.gantt_view', 'view_project', 'view_gantt'];
        $permissions = array_values(array_filter(
            json_decode($role->default_permissions ?: '[]', true) ?: [],
            fn($permission) => !in_array($permission, $remove, true)
        ));

        DB::table('project_roles')->where('id', $role->id)->update([
            'default_permissions' => json_encode($permissions),
            'updated_at' => now(),
        ]);
    }
};
