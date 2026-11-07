<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Task workload and progress tracking columns
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('priority');
            $table->timestamp('last_progress_updated_at')->nullable()->after('progress');
        });

        // 2. User job title and capacity
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('name');
            $table->unsignedSmallInteger('weekly_capacity_hours')->nullable()->after('company_name');
        });

        // 3. Task assignment allocation
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('allocation_percent')->default(100)->after('user_id');
            $table->timestamp('assigned_at')->nullable()->useCurrent()->after('allocation_percent');
        });

        // 4. Task assignment audit history
        Schema::create('task_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('previous_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->string('action', 50)->default('reassigned');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['task_id', 'created_at']);
        });

        // 5. Task update requests log
        Schema::create('task_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('target_user_id')->constrained('users');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['task_id', 'target_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_update_requests');
        Schema::dropIfExists('task_assignment_histories');

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropColumn(['allocation_percent', 'assigned_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'weekly_capacity_hours']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['estimated_hours', 'last_progress_updated_at']);
        });
    }
};

