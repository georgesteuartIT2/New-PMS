<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('closure_status', 24)->default('not_requested')->index();
            $table->foreignId('closure_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closure_requested_at')->nullable();
            $table->foreignId('closure_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closure_approved_at')->nullable();
            $table->text('closure_notes')->nullable();
            $table->json('closure_checklist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closure_requested_by');
            $table->dropConstrainedForeignId('closure_approved_by');
            $table->dropColumn(['closure_status', 'closure_requested_at', 'closure_approved_at', 'closure_notes', 'closure_checklist']);
        });
    }
};
