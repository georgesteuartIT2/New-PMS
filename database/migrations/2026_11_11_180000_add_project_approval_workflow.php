<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('approval_stage', 30)->nullable()->index();
            $t->boolean('plan_initialized')->default(false);
            $t->timestamp('plan_published_at')->nullable();
            $t->text('approval_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->dropConstrainedForeignId('owner_user_id');
            $t->dropColumn(['approval_stage', 'plan_initialized', 'plan_published_at', 'approval_reason']);
        });
    }
};
