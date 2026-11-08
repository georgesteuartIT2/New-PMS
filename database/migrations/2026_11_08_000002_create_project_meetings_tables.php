<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('scheduled'); // scheduled, completed, cancelled
            $table->string('ms_event_id', 255)->nullable()->index();
            $table->dateTime('ms_synced_at')->nullable();
            $table->string('ms_sync_status', 30)->default('pending'); // synced, failed, pending, local_only
            $table->text('ms_sync_error')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'starts_at']);
            $table->index(['created_by', 'starts_at']);
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255);
            $table->string('name', 255);
            $table->string('response_status', 30)->default('needsAction'); // needsAction, accepted, tentative, declined
            $table->timestamps();

            $table->unique(['meeting_id', 'email']);
            $table->index(['user_id', 'meeting_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
    }
};

