<?php
require dirname(__DIR__,3).'/vendor/autoload.php';
$app=require dirname(__DIR__,3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Project,ProjectInvitation,ProjectMembership,ProjectRole,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
$pmo=User::where('system_role','pmo')->where('account_status','active')->firstOrFail();
$manager=User::where('system_role','user')->where('account_status','active')->firstOrFail();
$role=ProjectRole::where('slug','pm')->firstOrFail();
$count=Project::count();
view()->share('errors',new Illuminate\Support\ViewErrorBag());
$check=function($condition,$label){if(!$condition)throw new Exception($label);echo 'PASS '.$label.PHP_EOL;};
DB::beginTransaction();
try {
 $create=function($suffix)use($pmo,$manager,$role){
  $project=Project::create(['code'=>'UI-QA-'.uniqid().$suffix,'name'=>'Review project '.$suffix,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'pending','pm_user_id'=>$manager->id,'created_by'=>$pmo->id]);
  ProjectInvitation::create(['project_id'=>$project->id,'user_id'=>$manager->id,'status'=>'pending']);
  ProjectMembership::create(['project_id'=>$project->id,'user_id'=>$manager->id,'project_role_id'=>$role->id]);
  return $project;
 };
 $project=$create('accept');
 $request=Request::create('/projects/'.$project->id,'GET');$request->setUserResolver(fn()=>$manager);$route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);$app->instance('request',$request);$app['auth']->guard()->setUser($manager);
 $html=app(App\Http\Controllers\ProjectController::class)->show($project,$request,app(App\Services\Access::class),app(App\Services\ProjectHealthService::class))->render();
 $check(str_contains($html,'Accept project') && str_contains($html,'data-show-dialog="decline-project-dialog"'),'pending manager sees accept and decline actions');
 $html=preg_replace('#(href|src)="http://(?:127.0.0.1:8000|localhost)/(css|js)/#','$1="file:///'.str_replace('\\','/',dirname(__DIR__,3)).'/public/$2/',$html);
 file_put_contents(__DIR__.'/manager-pending-project.html',$html);
 $respond=function($project,$actor,$decision)use($app){$r=Request::create('/projects/'.$project->id.'/respond','POST',['decision'=>$decision,'decline_reason'=>$decision==='decline'?'Capacity unavailable this month.':null]);$r->setUserResolver(fn()=>$actor);$app->instance('request',$r);$app['auth']->guard()->setUser($actor);return app(App\Http\Controllers\ProjectController::class)->respond($r,$project,app(App\Services\ProjectService::class));};
 try {$respond($project,$pmo,'accept');throw new Exception('Unauthorized acceptance allowed');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){$check($e->getStatusCode()===403,'PMO cannot accept on behalf of manager');}
 $respond($project,$manager,'accept');$project->refresh();
 $check($project->status==='active' && $project->baseline_captured_at!==null && $project->invitations()->first()->status==='accepted','PM acceptance activates project and records baseline');
 $decline=$create('decline');$respond($decline,$manager,'decline');
 $check($decline->fresh()->status==='declined' && $decline->invitations()->first()->status==='declined','PM decline records assignment decision');
}finally{DB::rollBack();}
$check(Project::count()===$count,'approval test changes rolled back');
