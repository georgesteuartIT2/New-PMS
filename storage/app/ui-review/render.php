<?php
require dirname(__DIR__,3).'/vendor/autoload.php';
$app = require dirname(__DIR__,3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kind=$argv[1]??'pmo';
$user=App\Models\User::where('system_role',$kind==='pmo'?'pmo':'user')->where('account_status','active')->where('must_change_password',false)->first();
if(!$user){fwrite(STDERR,"No eligible ".$kind." account.\n");exit(1);}
$paths=['/monitoring','/monitoring?tab=team','/administration','/dashboard','/projects','/tasks/my','/calendar','/notifications','/profile','/templates','/reports'];
if($kind==='pmo')$paths=array_merge($paths,['/projects/create','/users','/users/azure/search','/roles','/subsidiaries','/pmo/task-monitor','/pmo/team-monitor','/activity','/settings','/gantt','/templates/create']);
$access=$app->make(App\Services\Access::class);
$project=App\Models\Project::latest()->get()->first(fn($p)=>$access->project($user,$p,'view_project'));
if($project){
 foreach(['','/board','/calendar','/files','/gantt','/edit','/closure'] as $suffix)$paths[]='/projects/'.$project->id.$suffix;
}
$task=App\Models\Task::latest()->first();
if($task && $kind==='pmo')$paths[]='/tasks/'.$task->id;
$panelTask=App\Models\Task::latest()->get()->first(fn($t)=>$access->task($user,$t));
if($panelTask)$paths[]='/tasks/'.$panelTask->id.'/panel';
if($kind==='pmo'){
 $template=App\Models\ProjectTemplate::first();
 if($template)$paths[]='/templates/'.$template->id;
 $paths[]='/users/'.$user->id;
 $paths[]='/projects?attention=1';
 $paths[]='/monitoring?tab=tasks&filter=overdue';
 $paths[]='/monitoring?tab=team&workload=not_configured';
 $paths[]='/pmo/task-monitor?filter=overdue';
 $paths[]='/pmo/task-monitor?filter=completed';
 $paths[]='/pmo/task-monitor?filter=critical';
}
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
foreach($paths as $uri){
 $app['auth']->forgetGuards();
 $app['auth']->guard('web')->setUser($user);
 $request=Illuminate\Http\Request::create('http://127.0.0.1:8000'.$uri,'GET');
 $request->setUserResolver(fn()=>$user);
 $app->instance('request',$request);
 $response=$kernel->handle($request);
 $name=$kind.'-'.trim(preg_replace('/[^a-zA-Z0-9_-]/','-',$uri),'-');
 if($response->getStatusCode()===200){
  $html=$response->getContent();
  $html=preg_replace('#(href|src)="http://127.0.0.1:8000/(css|js)/#','$1="file:///'.str_replace('\\','/',dirname(__DIR__,3)).'/public/$2/',$html);
  file_put_contents(__DIR__.'/'.$name.'.html',$html);
 }
 echo $response->getStatusCode().' '.$uri."\n";
}