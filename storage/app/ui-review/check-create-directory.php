<?php
require dirname(__DIR__,3).'/vendor/autoload.php';
$app=require dirname(__DIR__,3).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,SocialAccount,Project,Subsidiary,ProjectRole};
use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;
$pmo=User::where('system_role','pmo')->where('account_status','active')->firstOrFail();$app['auth']->guard()->setUser($pmo);
$check=function($condition,$message){if(!$condition)throw new Exception($message);echo 'PASS '.$message.PHP_EOL;};
$before=User::count();$projectBefore=Project::count();
$remote=['id'=>'99000000-0000-4000-8000-'.str_pad((string)random_int(1,999999999),12,'0',STR_PAD_LEFT),'displayName'=>'Azure UI review person','mail'=>'ui-review-'.uniqid().'@example.invalid','userPrincipalName'=>'','accountEnabled'=>true,'companyName'=>''];
$graph=new class($remote) extends App\Services\AzureGraphService {public function __construct(public array $person){}public function get(string $id):array{return $this->person;}};
DB::beginTransaction();
try{
 $request=Request::create('/users/azure/add','POST',['azure_id'=>$remote['id']]);$request->headers->set('Accept','application/json');$request->setUserResolver(fn()=>$pmo);$app->instance('request',$request);
 $controller=app(App\Http\Controllers\UserController::class);$response=$controller->addAzure($request,$graph);$data=$response->getData(true);
 $id=$data['user']['id']??null;
 $check($response->getStatusCode()===200 && User::whereKey($id)->where('email',$remote['mail'])->exists(),'Azure import saves an active system user before project submission');
 $check(SocialAccount::where('user_id',$id)->where('provider','azure')->exists(),'Imported user has a Microsoft account link');
 $again=$controller->addAzure($request,$graph)->getData(true);
 $check(($again['user']['id']??null)===$id && User::count()===$before+1,'Repeated Azure selection reuses the saved account');
 $sub=Subsidiary::where('status','active')->firstOrFail();$team=ProjectRole::where('slug','team')->firstOrFail();$person=User::where('system_role','user')->where('account_status','active')->where('id','!=',$id)->firstOrFail();
 $request=Request::create('/projects','POST',['name'=>'UI review project','subsidiary_id'=>$sub->id,'starts_on'=>today()->format('Y-m-d'),'ends_on'=>today()->addWeek()->format('Y-m-d'),'mode'=>'blank','pm_user_id'=>$id,'action'=>'create','members'=>[['user_id'=>$person->id,'project_role_id'=>$team->id]]]);$request->setUserResolver(fn()=>$pmo);$app->instance('request',$request);
 app(App\Http\Controllers\ProjectController::class)->store($request,app(App\Services\ProjectService::class),app(App\Services\Access::class));
 $project=Project::where('name','UI review project')->latest('id')->firstOrFail();
 $check($project->pm_user_id===$id && $project->status==='pending' && $project->invitations()->where('user_id',$id)->exists(),'Imported manager can receive a pending project assignment');
 $check($project->memberships()->where('user_id',$person->id)->where('project_role_id',$team->id)->exists(),'Project submission retains selected team membership');
 // Render returned validation inputs, including PM, team and governance roles.
 $sponsor=ProjectRole::where('slug','ps')->first();$saved=['name'=>'Restored project','subsidiary_id'=>$sub->id,'starts_on'=>'2026-11-09','ends_on'=>'2026-12-01','pm_user_id'=>$id,'members'=>[['user_id'=>$person->id,'project_role_id'=>$team->id]]];
 if($sponsor){$stakeholder=User::where('system_role','user')->where('account_status','active')->whereNotIn('id',[$id,$person->id])->first();if($stakeholder)$saved['members'][]=['user_id'=>$stakeholder->id,'project_role_id'=>$sponsor->id];}
 app('session')->flashInput($saved);view()->share('errors',new Illuminate\Support\ViewErrorBag());
 $r=Request::create('http://127.0.0.1:8000/projects/create','GET');$r->setUserResolver(fn()=>$pmo);$r->setLaravelSession(app('session')->driver());$route=app('router')->getRoutes()->match($r);$r->setRouteResolver(fn()=>$route);$app->instance('request',$r);
 $html=app(App\Http\Controllers\ProjectController::class)->create($r,app(App\Services\Access::class))->render();
 $html=preg_replace('#(href|src)="http://(?:127.0.0.1:8000|localhost)/(css|js)/#','$1="file:///'.str_replace('\\','/',dirname(__DIR__,3)).'/public/$2/',$html);file_put_contents(__DIR__.'/pmo-project-create-restored.html',$html);
 $check(str_contains($html,'Restored project') && !str_contains($html,'quick-add-user-modal'),'Create form restores old fields and has no manual-user popup');
}finally{DB::rollBack();}
$check(User::count()===$before && Project::count()===$projectBefore,'All import and project test records rolled back; notifications not sent');
