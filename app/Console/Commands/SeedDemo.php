<?php
namespace App\Console\Commands;
use App\Models\{Project,ProjectRole,ProjectMembership,ProjectInvitation,ProjectTemplate,TemplateTask,Task,User};
use App\Services\ProjectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class SeedDemo extends Command {
 protected $signature='demo:seed';
 protected $description='Add fictional sample projects, roles and tasks to the local development application';
 public function handle(ProjectService $service):int {
  if(!app()->environment('local')){$this->error('This command may be run only when APP_ENV=local.');return self::FAILURE;}
  $pmo=User::where('system_role','pmo')->first();
  if(!$pmo){$this->error('Create the PMO administrator first using php artisan pmo:create.');return self::FAILURE;}
  $credentials=[];
  DB::transaction(function()use($pmo,&$credentials,$service){
   $people=[['Demo Manager','demo.manager@example.test'],['Demo Sponsor','demo.sponsor@example.test'],['Demo Owner','demo.owner@example.test'],['Demo Steering','demo.steering@example.test'],['Demo Member A','demo.member.a@example.test'],['Demo Member B','demo.member.b@example.test']];
   $accounts=[];
   foreach($people as [$name,$email]){
    $user=User::where('email',$email)->first();
    if(!$user){$pwd=Str::random(20);$user=User::create(['name'=>$name,'email'=>$email,'password'=>$pwd,'system_role'=>'user','account_status'=>'active','must_change_password'=>true]);$credentials[]=['email'=>$email,'password'=>$pwd];}
    $accounts[$email]=$user;
   }
   $pm=$accounts['demo.manager@example.test'];
   $template=ProjectTemplate::firstOrCreate(['name'=>'Website Delivery'],['description'=>'A simple reusable website project structure.','created_by'=>$pmo->id]);
   foreach([['DISCOVERY',null,'Discovery and requirements',2],['DESIGN',null,'Interface design',5],['WIREFRAMES','DESIGN','Wireframes',2],['UI','DESIGN','Detailed UI design',3],['BUILD',null,'Development',10],['QA',null,'Quality assurance',4],['LAUNCH',null,'Launch',2]] as $n=>$item){
    [$code,$parent,$name,$duration]=$item;TemplateTask::firstOrCreate(['project_template_id'=>$template->id,'code'=>$code],['parent_code'=>$parent,'title'=>$name,'duration_days'=>$duration,'sort_order'=>$n,'priority'=>'medium']);
   }
   $project=Project::firstOrCreate(['code'=>'DEMO-WEB'],['name'=>'Company Website Upgrade','description'=>'A sample active project with tasks, a manager and team members.','starts_on'=>now()->startOfMonth()->toDateString(),'ends_on'=>now()->addDays(45)->toDateString(),'status'=>'active','project_template_id'=>$template->id,'pm_user_id'=>$pm->id,'created_by'=>$pmo->id]);
   $assign=[[$pm,'pm'],[$accounts['demo.sponsor@example.test'],'ps'],[$accounts['demo.owner@example.test'],'po'],[$accounts['demo.steering@example.test'],'steering'],[$accounts['demo.member.a@example.test'],'team'],[$accounts['demo.member.b@example.test'],'team']];
   foreach($assign as [$person,$slug]){ProjectMembership::updateOrCreate(['project_id'=>$project->id,'user_id'=>$person->id],['project_role_id'=>ProjectRole::where('slug',$slug)->firstOrFail()->id]);}
   $project->update(['status'=>'active']);
   ProjectInvitation::firstOrCreate(['project_id'=>$project->id,'user_id'=>$pm->id,'status'=>'accepted'],['responded_at'=>now()]);
   $data=[['T001',null,'Requirements workshop','completed',100,-10,-8],['T002',null,'Design direction','in_progress',55,-7,5],['T003','T002','Homepage wireframes','completed',100,-7,-2],['T004','T002','Mobile layouts','in_progress',45,-1,5],['T005',null,'Frontend implementation','todo',0,4,20],['T006','T005','Build homepage','todo',0,4,11],['T007',null,'Quality assurance','todo',0,20,29],['T008',null,'Launch checklist','todo',0,29,36]];
   $tasks=[];
   foreach($data as $i=>$v){[$code,$parent,$title,$status,$progress,$from,$to]=$v;
    $task=Task::firstOrCreate(['project_id'=>$project->id,'code'=>$code],['title'=>$title,'starts_at'=>now()->addDays($from)->setTime(9,0),'ends_at'=>now()->addDays($to)->setTime(17,0),'status'=>$status,'progress'=>$progress,'sort_order'=>$i,'priority'=>'medium','created_by'=>$pmo->id]);$tasks[$code]=$task;
   }
   foreach($data as $v){if($v[1])$tasks[$v[0]]->update(['parent_task_id'=>$tasks[$v[1]]->id]);}
   $tasks['T004']->assignees()->sync([$accounts['demo.member.a@example.test']->id]);$tasks['T006']->assignees()->sync([$accounts['demo.member.b@example.test']->id]);
   $service->recalculate($project);
   $pending=Project::firstOrCreate(['code'=>'DEMO-APP'],['name'=>'Mobile App Pilot','description'=>'Pending Project Manager acceptance; other roles cannot view yet.','starts_on'=>now()->toDateString(),'ends_on'=>now()->addDays(40)->toDateString(),'status'=>'pending','pm_user_id'=>$pm->id,'created_by'=>$pmo->id]);
   ProjectMembership::updateOrCreate(['project_id'=>$pending->id,'user_id'=>$pm->id],['project_role_id'=>ProjectRole::where('slug','pm')->firstOrFail()->id]);
   ProjectInvitation::firstOrCreate(['project_id'=>$pending->id,'user_id'=>$pm->id,'status'=>'pending']);
  });
  $this->info('Sample roles, projects, template, tasks and invitations are now ready.');
  foreach($credentials as $row){$this->line($row['email'].'  |  Temporary password: '.$row['password']);}
  $this->warn('Only fictional sample data was created. Share credentials securely and never use demo accounts in production.');
  return self::SUCCESS;
 }
}
