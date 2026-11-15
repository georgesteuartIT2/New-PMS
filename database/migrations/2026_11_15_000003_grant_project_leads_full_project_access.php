<?php

use App\Models\ProjectRole;
use App\Services\Access;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect(Access::CATALOG)
            ->reject(fn ($permission) => $permission['category'] === 'company')
            ->keys()
            ->values()
            ->all();

        ProjectRole::whereIn('slug', ['pm', 'po'])->update([
            'default_permissions' => json_encode($permissions),
        ]);
    }

    public function down(): void
    {
        // Role policies may have been edited after this migration. Do not overwrite them.
    }
};
