<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('projects',fn(Blueprint $t)=>$t->json('setup_draft')->nullable());}public function down():void{Schema::table('projects',fn(Blueprint $t)=>$t->dropColumn('setup_draft'));}};