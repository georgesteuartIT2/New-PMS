<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('projects',fn(Blueprint $table)=>$table->json('plan_assignment_drafts')->nullable());}
 public function down():void {Schema::table('projects',fn(Blueprint $table)=>$table->dropColumn('plan_assignment_drafts'));}
};