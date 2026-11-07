<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Null for historical projects: never invent a previous baseline.
            $table->date('baseline_starts_on')->nullable()->after('ends_on');
            $table->date('baseline_ends_on')->nullable()->after('baseline_starts_on');
            $table->timestamp('baseline_captured_at')->nullable()->after('baseline_ends_on');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dateTime('baseline_starts_at')->nullable()->after('ends_at');
            $table->dateTime('baseline_ends_at')->nullable()->after('baseline_starts_at');
        });

        Schema::create('task_delays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->string('reason_code', 60);
            $table->text('details')->nullable();
            $table->string('responsible_party_type', 32)->default('unknown');
            $table->string('responsible_party_name', 160)->nullable();
            $table->foreignId('action_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revised_expected_end_at')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_delays');
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['baseline_starts_at', 'baseline_ends_at']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['baseline_starts_on', 'baseline_ends_on', 'baseline_captured_at']));
    }
};
