<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Project,Task,ProjectRole,ProjectMembership};
class UnifiedTaskWorkspaceTest extends TestCase {
 use RefreshDatabase;
 private function person(){return User::create(['name'=>'Workspace user','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>'user','account_status'=>'active','must_change_password'=>false]);}
 private function project($pm){return Project::create(['name'=>'Scoped project','code'=>uniqid('P-'),'created_by'=>$pm->id,'pm_user_id'=>$pm->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);}
 private function task($p,$title='Work'){return Task::create(['project_id'=>$p->id,'code'=>uniqid('T-'),'title'=>$title,'created_by'=>$p->created_by,'starts_at'=>now(),'ends_at'=>now()->addDay()]);}
 public function test_union_has_no_duplicates_and_filters_preserve_authorized_scope():void{
  $user=$this->person();$other=$this->person();$managed=$this->project($user);$outside=$this->project($other);
  $both=$this->task($managed,'My managed task');$both->assignees()->attach($user->id);$monitored=$this->task($managed,'Monitored only');$assigned=$this->task($outside,'Assigned only');$assigned->assignees()->attach($user->id);$hidden=$this->task($outside,'Private task');
  $r=$this->actingAs($user)->getJson(route('tasks.mine'));$r->assertOk()->assertJsonPath('metrics.total_tasks',3)->assertJsonCount(3,'tasks.data');$rows=collect($r->json('tasks.data'))->keyBy('id');$this->assertTrue($rows[$both->id]['is_my_task']);$this->assertTrue($rows[$both->id]['is_monitored_task']);$this->assertFalse($rows[$monitored->id]['is_my_task']);$this->assertFalse($rows[$assigned->id]['is_monitored_task']);$this->assertFalse($rows->has($hidden->id));
  $this->getJson(route('tasks.mine',['scope'=>'assigned']))->assertOk()->assertJsonCount(2,'tasks.data');$this->getJson(route('tasks.mine',['scope'=>'managed']))->assertOk()->assertJsonCount(2,'tasks.data');$this->getJson(route('tasks.mine',['scope'=>'managed','project_id'=>$outside->id]))->assertJsonCount(0,'tasks.data');$this->getJson(route('tasks.mine',['q'=>'Private']))->assertJsonCount(0,'tasks.data');
  $this->get(route('tasks.mine'))->assertOk()->assertSee('Your involvement')->assertDontSee('projects-kpi-card')->assertDontSee('task-details-drawer');
 }
 public function test_view_only_monitor_can_read_but_cannot_update_tasks():void{
  $user=$this->person();$p=$this->project($this->person());$task=$this->task($p);$role=ProjectRole::create(['name'=>'Observer','slug'=>'observer','default_permissions'=>['project.view','tasks.view_all']]);ProjectMembership::create(['project_id'=>$p->id,'user_id'=>$user->id,'project_role_id'=>$role->id]);
  $this->actingAs($user)->get(route('tasks.mine'))->assertOk()->assertSee('Monitoring')->assertDontSee('class="quick-status-select"',false);$this->patchJson(route('tasks.update',$task),['status'=>'completed'])->assertForbidden();
 }
 public function test_status_filters_and_pagination_work_on_the_combined_set():void{
  $user=$this->person();$p=$this->project($user);for($i=0;$i<23;$i++)$this->task($p);$done=$this->task($p);$done->update(['status'=>'completed']);
  $this->actingAs($user)->getJson(route('tasks.mine',['scope'=>'managed']))->assertJsonPath('tasks.total',24)->assertJsonCount(20,'tasks.data');$this->getJson(route('tasks.mine',['scope'=>'managed','page'=>2]))->assertJsonCount(4,'tasks.data');$this->getJson(route('tasks.mine',['filter'=>'completed']))->assertJsonCount(1,'tasks.data')->assertJsonPath('tasks.data.0.id',$done->id);$this->getJson(route('tasks.mine',['filter'=>'open']))->assertJsonPath('tasks.total',23);
 }
 public function test_inline_assignment_is_pm_only_and_project_scoped():void {
  $pm=$this->person();$member=$this->person();$outsider=$this->person();$p=$this->project($pm);$task=$this->task($p);
  $role=ProjectRole::create(['name'=>'Member','slug'=>'assign-test','default_permissions'=>['project.view']]);ProjectMembership::create(['project_id'=>$p->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
  $this->actingAs($pm)->get(route('tasks.mine'))->assertOk()->assertSee('data-pm-assign-url',false)->assertSee($member->name);
  $this->postJson(route('tasks.pm-assign',$task),['user_id'=>$outsider->id])->assertUnprocessable();
  $this->actingAs($outsider)->postJson(route('tasks.pm-assign',$task),['user_id'=>$member->id])->assertForbidden();
  $this->actingAs($pm)->postJson(route('tasks.pm-assign',$task),['user_id'=>$member->id])->assertOk();
  $this->assertTrue($task->assignees()->where('users.id',$member->id)->exists());
  $this->postJson(route('tasks.pm-assign',$task),['user_id'=>$pm->id])->assertStatus(409);
  $this->getJson(route('tasks.mine',['attention'=>'needs_owner','filter'=>'open']))->assertJsonPath('tasks.total',0);
 }
 public function test_numbers_follow_project_hierarchy_even_when_parent_is_filtered_out():void {
  $user=$this->person();$p=$this->project($user);$parent=$this->task($p,'Parent');$parent->update(['sort_order'=>1]);$a=$this->task($p,'First child');$a->update(['parent_task_id'=>$parent->id,'sort_order'=>1]);$b=$this->task($p,'Second child');$b->update(['parent_task_id'=>$parent->id,'sort_order'=>2]);$main=$this->task($p,'Second main');$main->update(['sort_order'=>2]);
  $this->actingAs($user)->getJson(route('tasks.mine',['q'=>'Second child']))->assertOk()->assertJsonCount(1,'tasks.data')->assertJsonPath('tasks.data.0.display_number','1.2');
  $this->getJson(route('tasks.mine',['q'=>'Second main']))->assertJsonPath('tasks.data.0.display_number','2');
  $this->assertSame($b->code,$b->fresh()->code);
 }
}
