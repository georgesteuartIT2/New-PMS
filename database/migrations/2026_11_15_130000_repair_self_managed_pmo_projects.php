<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $pmRoleId = DB::table('project_roles')->where('slug', 'pm')->value('id');

        DB::table('projects')
            ->join('users', 'users.id', '=', 'projects.owner_user_id')
            ->whereNull('projects.pm_user_id')
            ->where('projects.status', 'active')
            ->where('projects.approval_stage', 'planning')
            ->where('users.system_role', 'pmo')
            ->where('users.account_status', 'active')
            ->select('projects.id', 'projects.owner_user_id')
            ->orderBy('projects.id')
            ->each(function ($project) use ($pmRoleId) {
                DB::table('projects')->where('id', $project->id)->update([
                    'pm_user_id' => $project->owner_user_id,
                    'updated_at' => now(),
                ]);

                if ($pmRoleId) {
                    DB::table('project_memberships')
                        ->where('project_id', $project->id)
                        ->where('user_id', $project->owner_user_id)
                        ->update(['project_role_id' => $pmRoleId, 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // This targeted repair is intentionally not reversed.
    }
};
