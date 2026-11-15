<?php

use App\Models\ProjectRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'project.view', 'project.gantt_view', 'project.reports_view', 'project.budget_view',
            'tasks.view_all', 'tasks.download_attachments', 'tasks.view_critical_path',
        ];

        ProjectRole::whereIn('slug', ['ps', 'steering'])->update([
            'default_permissions' => json_encode($permissions),
        ]);
    }

    public function down(): void
    {
        // Preserve any role policy edits made after deployment.
    }
};
