<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Project,Task,ProjectInvitation};
use App\Services\ProjectService;
class CompletionProgressAndApprovalsTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role='pmo'):User{return User::create(['name'=>'Reviewer','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 private function project(User $user,string $status='active'):Project{return Project::create(['name'=>'Review project','code'=>uniqid('QA-'),'created_by'=>$user->id,'pm_user_id'=>$user->id,'status'=>$status,'starts_on'=>today(),'ends_on'=>today()->addWeek()]);}
 private function task(Project $p,?Task $parent=null,string $status='todo',int $progress=0):Task{return Task::create(['project_id'=>$p->id,'parent_task_id'=>$parent?->id,'code'=>uniqid('T-'),'title'=>'Task','created_by'=>$p->created_by,'starts_at'=>now(),'ends_at'=>now()->addDay(),'status'=>$status,'progress'=>$progress,'sort_order'=>1]);}
 public function test_nested_parents_use_completed_leaves_and_partial_work_does_not_inflate_project():void{
  $u=$this->user();$p=$this->project($u);$root=$this->task($p);$nested=$this->task($p,$root);$a=$this->task($p,$nested,'completed');$this->task($p,$nested,'completed');$this->task($p,$nested,'in_progress',80);$this->task($p,$root);$this->task($p,null,'completed');
  app(ProjectService::class)->recalculate($p);$this->assertSame(60,$p->fresh()->progress);$this->assertSame(50,$root->fresh()->progress);$this->assertSame(67,$nested->fresh()->progress);$this->assertSame('active',$p->fresh()->status);
  $html=$this->actingAs($u)->get(route('projects.show',$p))->getContent();$html=preg_replace('#(href|src)="http://localhost/(css|js)/#','$1="file:///'.str_replace('\\','/',base_path()).'/public/$2/',$html);file_put_contents(storage_path('app/ui-review/progress-fixture.html'),$html);
  $r=$this->actingAs($u)->patchJson(route('tasks.update',$a),['status'=>'in_progress']);$r->assertOk()->assertJsonPath('progress',0)->assertJsonPath('project_progress',40)->assertJsonPath('total_tasks',5)->assertJsonPath('completed_tasks',2);$this->assertSame(25,$root->fresh()->progress);file_put_contents(storage_path('app/ui-review/progress-response.json'),$r->getContent());
  $this->actingAs($u)->patchJson(route('tasks.update',$root),['status'=>'completed'])->assertStatus(422);
 }
 public function test_completion_todo_hold_and_empty_project_rules():void{
  $u=$this->user();$p=$this->project($u);$t=$this->task($p,null,'in_progress',45);app(ProjectService::class)->recalculate($p);$this->assertSame(0,$p->fresh()->progress);
  $this->actingAs($u)->patchJson(route('tasks.update',$t),['status'=>'on_hold'])->assertOk()->assertJsonPath('progress',45);
  $this->patchJson(route('tasks.update',$t),['status'=>'completed'])->assertOk()->assertJsonPath('project_progress',100);$this->assertSame('active',$p->fresh()->status);
  $this->patchJson(route('tasks.update',$t),['status'=>'todo','progress'=>100])->assertOk()->assertJsonPath('progress',0)->assertJsonPath('project_progress',0);
  $t->delete();app(ProjectService::class)->recalculate($p);$this->assertSame(0,$p->fresh()->progress);
 }
 public function test_unrelated_user_cannot_update_task():void{
  $p=$this->project($this->user());$t=$this->task($p);$this->actingAs($this->user('user'))->patchJson(route('tasks.update',$t),['status'=>'completed'])->assertForbidden();$this->assertSame('todo',$t->fresh()->status);
 }
 public function test_manager_approvals_have_separate_paginated_queue_and_review_card():void{
  $manager=$this->user('user');for($i=1;$i<=8;$i++){$p=$this->project($manager,'pending');$p->update(['name'=>'Approval '.$i]);ProjectInvitation::create(['project_id'=>$p->id,'user_id'=>$manager->id,'status'=>'pending']);}
  $r=$this->actingAs($manager)->get(route('dashboard'));$r->assertOk()->assertSee('Project approvals')->assertViewHas('approvalQueue',fn($q)=>$q->total()===8 && $q->count()===5)->assertViewHas('attentionItems',fn($items)=>!$items->contains(fn($item)=>$item['type']==='invitation'));
  $this->get(route('dashboard',['approvals_page'=>2]))->assertOk()->assertViewHas('approvalQueue',fn($q)=>$q->count()===3);
  $review=$this->get(route('projects.show',$p));$review->assertOk()->assertSee('Review your project assignment')->assertSee('Accept project')->assertSee('Decline');
  foreach(['approval-queue'=>$r->getContent(),'approval-review'=>$review->getContent()] as $name=>$html){$html=preg_replace('#(href|src)="http://localhost/(css|js)/#','$1="file:///'.str_replace('\\','/',base_path()).'/public/$2/',$html);file_put_contents(storage_path('app/ui-review/'.$name.'.html'),$html);}
  $this->actingAs($this->user('user'))->get(route('dashboard'))->assertOk()->assertViewHas('approvalQueue',fn($q)=>$q->total()===0);
 }
 public function test_adding_and_removing_work_recalculates_completion_and_keeps_project_active():void{
  $u=$this->user();$p=$this->project($u);$a=$this->task($p,null,'completed');app(ProjectService::class)->recalculate($p);$this->assertSame(100,$p->fresh()->progress);
  $b=$this->task($p,null,'in_progress',75);app(ProjectService::class)->recalculate($p);$this->assertSame(50,$p->fresh()->progress);
  $b->delete();app(ProjectService::class)->recalculate($p);$this->assertSame(100,$p->fresh()->progress);$this->assertSame('active',$p->fresh()->status);
 }
 public function test_quick_assign_only_accepts_active_project_people():void{
  $u=$this->user();$p=$this->project($u);$t=$this->task($p);$member=$this->user('user');$outside=$this->user('user');
  $role=\App\Models\ProjectRole::create(['name'=>'Team Member','slug'=>'team','is_custom'=>false]);\App\Models\ProjectMembership::create(['project_id'=>$p->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
  $this->actingAs($u)->postJson(route('tasks.assign',$t),['user_id'=>$member->id])->assertOk()->assertJsonPath('assignees.0.name',$member->name);$this->assertSame(1,$t->assignees()->count());
  $this->postJson(route('tasks.assign',$t),['user_id'=>$member->id])->assertOk();$this->assertSame(1,$t->assignees()->count());
  $this->postJson(route('tasks.assign',$t),['user_id'=>$outside->id])->assertStatus(422);
  $member->update(['account_status'=>'inactive']);$this->postJson(route('tasks.assign',$t),['user_id'=>$member->id])->assertStatus(422);
  $this->actingAs($outside)->postJson(route('tasks.assign',$t),['user_id'=>$outside->id])->assertForbidden();
  $p->update(['status'=>'archived']);$this->actingAs($u)->postJson(route('tasks.assign',$t),['user_id'=>$u->id])->assertForbidden();
 }
 public function test_only_pmo_can_delete_project_with_confirmation_and_cascades():void{
  $admin=$this->user();$manager=$this->user('user');$project=$this->project($manager);$task=$this->task($project);$other=$this->project($admin);
  ProjectInvitation::create(['project_id'=>$project->id,'user_id'=>$manager->id,'status'=>'accepted']);$task->assignees()->attach($manager->id);
  $this->actingAs($manager)->get(route('projects.show',$project))->assertOk()->assertDontSee('data-delete-project-url',false);
  $this->deleteJson(route('projects.destroy',$project),['confirm_delete'=>1])->assertForbidden();$this->assertDatabaseHas('projects',['id'=>$project->id]);
  $this->actingAs($admin)->get(route('projects.index'))->assertOk()->assertSee('data-delete-project-url',false);
  $this->deleteJson(route('projects.destroy',$project))->assertStatus(422);$this->assertDatabaseHas('projects',['id'=>$project->id]);
  $this->delete(route('projects.destroy',$project),['confirm_delete'=>1])->assertRedirect(route('projects.index'));
  $this->assertDatabaseMissing('projects',['id'=>$project->id]);$this->assertDatabaseMissing('tasks',['id'=>$task->id]);$this->assertDatabaseMissing('project_invitations',['project_id'=>$project->id]);$this->assertDatabaseMissing('task_assignments',['task_id'=>$task->id]);
  $this->assertDatabaseHas('projects',['id'=>$other->id]);$this->assertDatabaseHas('users',['id'=>$manager->id]);$this->assertDatabaseHas('activity_logs',['action'=>'project.deleted','subject_id'=>$project->id]);
 }
 public function test_critical_explanations_follow_actual_dependencies_and_are_read_only():void{
  $u=$this->user();$p=$this->project($u);$a=$this->task($p);$b=$this->task($p);$c=$this->task($p);$parallel=$this->task($p);$b->dependencies()->attach($a->id);$c->dependencies()->attach($b->id);
  $before=app(\App\Services\ProjectHealthService::class)->assess($p)['critical_ids'];
  $this->actingAs($u)->getJson(route('tasks.critical-explanation',$a))->assertOk()->assertJsonPath('critical',true)->assertJsonCount(0,'predecessors')->assertJsonPath('successors.0.code',$b->code);
  $this->getJson(route('tasks.critical-explanation',$b))->assertOk()->assertJsonPath('float_minutes',0)->assertJsonPath('predecessors.0.code',$a->code)->assertJsonPath('successors.0.code',$c->code);
  $this->getJson(route('tasks.critical-explanation',$c))->assertOk()->assertJsonCount(0,'successors')->assertJsonPath('predecessors.0.code',$b->code);
  $this->getJson(route('tasks.critical-explanation',$parallel))->assertOk()->assertJsonPath('critical',false);
  $this->assertSame($before,app(\App\Services\ProjectHealthService::class)->assess($p)['critical_ids']);$this->assertSame('todo',$b->fresh()->status);
  $this->actingAs($this->user('user'))->getJson(route('tasks.critical-explanation',$b))->assertForbidden();
 }
 public function test_critical_explanation_includes_inherited_parent_dependencies():void{
  $u=$this->user();$p=$this->project($u);$before=$this->task($p);$parent=$this->task($p);$child=$this->task($p,$parent);$parent->dependencies()->attach($before->id);
  $this->actingAs($u)->getJson(route('tasks.critical-explanation',$child))->assertOk()->assertJsonPath('critical',true)->assertJsonPath('predecessors.0.code',$before->code);
 }
}
