<?php
require dirname(__DIR__,3).'/vendor/autoload.php';
$app=require dirname(__DIR__,3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Project,User,ProjectRole,ProjectTemplate};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
$user=User::where('system_role','pmo')->where('account_status','active')->firstOrFail();
$app['auth']->guard()->setUser($user);
$project=Project::where('status','active')->firstOrFail();
$access=app(App\Services\Access::class);
$role=ProjectRole::where('slug','!=','pm')->firstOrFail();
$person=User::where('system_role','user')->where('account_status','active')->where('id','!=',$project->pm_user_id)->firstOrFail();
$original=$project->getAttributes();$taskIds=$project->tasks()->pluck('id')->all();
$base=['name'=>$project->name,'description'=>$project->description,'starts_on'=>$project->starts_on->format('Y-m-d'),'ends_on'=>$project->ends_on->format('Y-m-d'),'status'=>$project->status];
$save=function($extra)use($base,$project,$user,$access,$app){$r=Request::create('/projects/'.$project->id,'PUT',array_merge($base,$extra));$r->headers->set('Accept','application/json');$r->setUserResolver(fn()=>$user);$app->instance('request',$r);return app(App\Http\Controllers\ProjectController::class)->update($r,$project,$access);};
DB::beginTransaction();
try{
 $save(['project_template_id'=>null,'edit_team'=>1,'members'=>[['user_id'=>$person->id,'project_role_id'=>$role->id]]]);
 if($project->fresh()->project_template_id!==null || !$project->memberships()->where('user_id',$person->id)->where('project_role_id',$role->id)->exists())throw new Exception('Save failed');
 echo "PASS blank setup + team save\n";
 $template=ProjectTemplate::where('status','active')->firstOrFail();
 $save(['project_template_id'=>$template->id,'edit_team'=>1,'members'=>[]]);
 if($project->fresh()->project_template_id!==$template->id || $project->memberships()->where('user_id',$person->id)->exists())throw new Exception('Template/remove failed');
 if($taskIds!==$project->tasks()->pluck('id')->all())throw new Exception('Existing tasks changed');
 echo "PASS template switch + team removal + existing tasks preserved\n";
 try{$save(['edit_team'=>1,'members'=>[['user_id'=>$person->id,'project_role_id'=>$role->id],['user_id'=>$person->id,'project_role_id'=>$role->id]]]);throw new Exception('Duplicates accepted');}catch(Illuminate\Validation\ValidationException $e){echo "PASS duplicate users rejected\n";}
 try{$save(['edit_team'=>1,'members'=>[['user_id'=>$person->id,'project_role_id'=>ProjectRole::where('slug','pm')->value('id')]]]);throw new Exception('PM role accepted');}catch(Illuminate\Validation\ValidationException $e){echo "PASS PM role protected\n";}
 $r=Request::create('/projects/'.$project->id.'/edit?popup=1','GET');$r->setUserResolver(fn()=>$user);$app->instance('request',$r);
 $html=app(App\Http\Controllers\ProjectWorkspaceController::class)->edit($r,$project,$access)->render();
 if(!str_contains($html,'data-editor-add-member')||!str_contains($html,'name="project_template_id"'))throw new Exception('Shared popup missing fields');
 echo "PASS table editor shared view renders\n";
}finally{DB::rollBack();}
if(Project::find($project->id)->getAttributes()!=$original)throw new Exception('Rollback failed');
echo "PASS all database changes rolled back\n";
