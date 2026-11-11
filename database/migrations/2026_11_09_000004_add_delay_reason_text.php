<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('task_delays',fn(Blueprint $t)=>$t->string('reason_text',500)->nullable());}public function down():void{Schema::table('task_delays',fn(Blueprint $t)=>$t->dropColumn('reason_text'));}};