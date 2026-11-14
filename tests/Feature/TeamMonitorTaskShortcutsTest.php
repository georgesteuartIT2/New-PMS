<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Project,Task};
use App\Services\EmployeeWorkloadService;
class TeamMonitorTaskShortcutsTest extends TestCase {
 use RefreshDatabase;
 public function test_priority_and_task_shortcuts_preserve_project_and_active_scope():void {
  $pmo=User::create(['name'=>'PMO','email'=>'pmo@example.test','password'=>'secret123','system_role'=>'pmo','account_status'=>'active','must_change_password'=>false]);
  $person=User::create(['name'=>'Owner','email'=>'owner@example.test','password'=>'secret123','system_role'=>'user','account_status'=>'active','must_change_password'=>false]);
  $projects=[];foreach(['active','active','draft'] as $i=>$status)$projects[]=Project::create(['name'=>'Project '.$i,'code'=>'P'.$i,'created_by'=>$pmo->id,'pm_user_id'=>$person->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>$status]);
  $tasks=[];foreach($projects as $i=>$project){$t=Task::create(['project_id'=>$project->id,'code'=>'T'.$i,'title'=>'Work '.$i,'created_by'=>$pmo->id,'starts_at'=>now()->subWeek(),'ends_at'=>now()->addDays($i===1?-2:2)]);$t->assignees()->attach($person->id);$tasks[]=$t;}
  $service=app(EmployeeWorkloadService::class);
  $this->assertSame($tasks[1]->id,$service->calculateEmployeeWorkload($person)['priority_task_id']);
  $scoped=$service->calculateEmployeeWorkload($person,[],null,$projects[0]->id);
  $this->assertSame($tasks[0]->id,$scoped['priority_task_id']);$this->assertSame(1,$scoped['open_tasks_count']);
  $this->assertSame(0,$service->getFilteredEmployees($pmo,['project_id'=>$projects[0]->id,'has_overdue'=>1])->total());
  $ordered=$service->getFilteredEmployees($pmo,['attention_first'=>true]);
  $this->assertSame($person->id,$ordered->items()[0]->id);
  $this->assertSame($projects[0]->name,$scoped['active_project_names'][0]['name']);
  $this->actingAs($pmo)->get(route('pmo.team-monitor'))->assertOk()->assertSee('Task to follow up')->assertSee('With overdue tasks')->assertDontSee('<th>Completed</th>',false)->assertDontSee('<th>Critical</th>',false)->assertSee('Open task')->assertSee(route('projects.show',[$projects[1]->id,'focus_task'=>$tasks[1]->id]).'#tasks',false)->assertDontSee('Workload Status')->assertDontSee('View Workload');
  $this->getJson(route('pmo.task-monitor',['user_id'=>$person->id,'active_only'=>1,'filter'=>'open']))->assertOk()->assertJsonCount(2,'tasks.data');
  $this->getJson(route('pmo.task-monitor',['user_id'=>$person->id,'active_only'=>1,'project_id'=>$projects[0]->id,'filter'=>'open']))->assertOk()->assertJsonCount(1,'tasks.data')->assertJsonPath('tasks.data.0.id',$tasks[0]->id);
 }
}
