<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('template_tasks', 'description')) {
            Schema::table('template_tasks', function (Blueprint $t) {
                $t->text('description')->nullable()->after('title');
            });
        }
    }

    public function down(): void {
        if (Schema::hasColumn('template_tasks', 'description')) {
            Schema::table('template_tasks', function (Blueprint $t) {
                $t->dropColumn('description');
            });
        }
    }
};

