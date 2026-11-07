<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('project_templates', function (Blueprint $t) {
            $t->string('category')->default('General')->after('name')->index();
            $t->unsignedSmallInteger('estimated_duration')->default(14)->after('category');
            $t->string('duration_unit')->default('working_days')->after('estimated_duration');
            $t->date('reference_start_date')->nullable()->after('duration_unit');
            $t->string('working_hours')->default('08:30 - 17:00')->after('reference_start_date');
            $t->string('status')->default('active')->after('working_hours')->index();
        });

        Schema::table('template_tasks', function (Blueprint $t) {
            $t->date('start_date')->nullable()->after('duration_days');
            $t->string('start_time', 10)->default('08:30')->nullable()->after('start_date');
            $t->date('end_date')->nullable()->after('start_time');
            $t->string('end_time', 10)->default('17:00')->nullable()->after('end_date');
            $t->string('dependency_code')->nullable()->after('end_time');
            $t->foreignId('suggested_role_id')->nullable()->after('dependency_code')->constrained('project_roles')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('template_tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('suggested_role_id');
            $t->dropColumn(['start_date', 'start_time', 'end_date', 'end_time', 'dependency_code']);
        });

        Schema::table('project_templates', function (Blueprint $t) {
            $t->dropColumn(['category', 'estimated_duration', 'duration_unit', 'reference_start_date', 'working_hours', 'status']);
        });
    }
};

