<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        $usedNames = [];

        DB::table('project_templates')->orderBy('id')->get(['id', 'name'])->each(function ($template) use (&$usedNames) {
            $baseName = trim((string) $template->name);
            $normalizedName = Str::lower($baseName);

            if (!isset($usedNames[$normalizedName])) {
                $usedNames[$normalizedName] = true;
                return;
            }

            $copyNumber = 2;
            do {
                $suffix = ' (' . $copyNumber . ')';
                $candidate = mb_substr($baseName, 0, 160 - mb_strlen($suffix)) . $suffix;
                $normalizedCandidate = Str::lower($candidate);
                $copyNumber++;
            } while (isset($usedNames[$normalizedCandidate]));

            DB::table('project_templates')->where('id', $template->id)->update(['name' => $candidate]);
            $usedNames[$normalizedCandidate] = true;
        });

        Schema::table('project_templates', function (Blueprint $table) {
            $table->unique('name', 'project_templates_name_unique');
        });
    }

    public function down(): void {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropUnique('project_templates_name_unique');
        });
    }
};
