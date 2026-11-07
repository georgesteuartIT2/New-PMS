<?php
/** Run from project root: php tests/smoke.php. Does not require MySQL. */
require dirname(__DIR__).'/vendor/autoload.php';

use App\Services\{CriticalPathCalculator, WorkingSchedule, TemplateScheduleService};
use App\Models\{ProjectTemplate, TemplateTask};
use Carbon\Carbon;

$pass=0;
function check($condition,string $title):void{
 global $pass;
 if(!$condition){fwrite(STDERR,"FAIL: {$title}\n");exit(1);}
 echo "PASS: {$title}\n";$pass++;
}
$cpm = (new CriticalPathCalculator())->calculate([
 1=>['duration'=>3,'predecessors'=>[]],2=>['duration'=>5,'predecessors'=>[1]],
 3=>['duration'=>2,'predecessors'=>[1]],4=>['duration'=>4,'predecessors'=>[2,3]],
]);
check($cpm['duration']===12 && $cpm['path']===[1,2,4],'critical path and float');
try{(new CriticalPathCalculator())->calculate([1=>['duration'=>1,'predecessors'=>[2]],2=>['duration'=>1,'predecessors'=>[1]]]);check(false,'cycle rejection');}
catch(InvalidArgumentException){check(true,'cycle rejection');}
$calendar=new WorkingSchedule();
check($calendar->hours('08:30 AM - 05:00 PM')===['08:30','17:00'],'company working hours AM/PM');
check($calendar->minutesBetween(Carbon::parse('2026-11-13 16:00'),Carbon::parse('2026-11-16 10:00'))===120,'weekend excluded from working minutes');
check($calendar->addMinutes(Carbon::parse('2026-11-13 16:00'),120)->format('Y-m-d H:i')==='2026-11-16 10:00','forecast uses working days');

$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$template=new ProjectTemplate(['name'=>'Office rollout','estimated_duration'=>10,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09']);
$template->setRelation('tasks',collect([new TemplateTask(['code'=>'A','title'=>'Inspection','start_date'=>'2026-11-09','start_time'=>'09:00','end_date'=>'2026-11-10','end_time'=>'17:00','duration_days'=>2,'sort_order'=>0])]));
$scheduler=new TemplateScheduleService();
$summary=$scheduler->calculate($template,'2026-11-10');
check($summary['deadline']==='2026-11-23','new project receives correct working-day deadline');
[$start,$end]=$scheduler->mapTaskDates($template,$template->tasks->first(),Carbon::parse('2026-11-10'));
check($start->format('Y-m-d H:i')==='2026-11-10 09:00' && $end->format('Y-m-d H:i')==='2026-11-11 17:00','template task dates shift to new project');

$compiler=$app->make('blade.compiler');
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/views'));
$n=0;
foreach($iterator as $file){
 if(!$file->isFile() || !str_ends_with($file->getFilename(),'.blade.php'))continue;
 $compiler->compileString(file_get_contents($file->getPathname()));$n++;
}
check($n>=30,'all '.$n.' Blade view sources compiled');
echo "\n{$pass} checks passed. Full database, mail and Azure integration tests still required.\n";
