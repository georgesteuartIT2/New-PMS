<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Project;
use App\Services\ProjectService;
class RecalculateProjectProgress extends Command {
 protected $signature='projects:recalculate-progress {project? : Project ID; omit for all projects}';
 protected $description='Recalculate parent and project progress from completed executable tasks';
 public function handle(ProjectService $service):int{
  $query=Project::query();if($this->argument('project'))$query->whereKey($this->argument('project'));
  $count=0;$query->orderBy('id')->chunkById(100,function($projects)use($service,&$count){foreach($projects as $project){$service->recalculate($project);$count++;}});
  $this->info('Recalculated '.$count.' projects. Project status and baselines were retained.');return self::SUCCESS;
 }
}
