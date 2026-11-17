<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('project_handovers',function(Blueprint $table){$table->id();$table->foreignId('project_id')->constrained()->cascadeOnDelete();$table->string('role');$table->foreignId('previous_user_id')->nullable()->constrained('users')->nullOnDelete();$table->foreignId('user_id')->constrained('users');$table->foreignId('requested_by')->constrained('users');$table->text('note');$table->string('status')->default('pending');$table->timestamps();});}
 public function down():void{Schema::dropIfExists('project_handovers');}
};
