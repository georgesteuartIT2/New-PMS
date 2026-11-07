<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subsidiaries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->string('azure_company_name')->nullable()->index();
            $t->text('description')->nullable();
            $t->unsignedInteger('project_counter')->default(0);
            $t->string('status')->default('active')->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('projects', function (Blueprint $t) {
            $t->foreignId('subsidiary_id')->nullable()->after('id')->constrained('subsidiaries')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('subsidiary_id')->nullable()->after('system_role')->constrained('subsidiaries')->nullOnDelete();
            $t->string('company_name')->nullable()->after('subsidiary_id');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('subsidiary_id');
            $t->dropColumn('company_name');
        });

        Schema::table('projects', function (Blueprint $t) {
            $t->dropConstrainedForeignId('subsidiary_id');
        });

        Schema::dropIfExists('subsidiaries');
    }
};

