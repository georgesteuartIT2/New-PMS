<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('projects',fn(Blueprint $t)=>$t->decimal('planned_budget',15,2)->nullable());Schema::create('project_expenses',function(Blueprint $t){$t->id();$t->foreignId('project_id')->constrained()->cascadeOnDelete();$t->decimal('amount',15,2);$t->string('description',240);$t->date('spent_on');$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();});}
 public function down():void {Schema::dropIfExists('project_expenses');Schema::table('projects',fn(Blueprint $t)=>$t->dropColumn('planned_budget'));}
};