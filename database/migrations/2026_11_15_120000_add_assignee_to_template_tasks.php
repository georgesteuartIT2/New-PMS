<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('template_tasks', function (Blueprint $table) {
            $table->foreignId('assignee_user_id')->nullable()->after('suggested_role_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_user_id');
        });
    }
};
