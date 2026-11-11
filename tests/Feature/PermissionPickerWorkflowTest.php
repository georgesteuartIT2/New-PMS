<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,ProjectRole,UserPermission};
use App\Services\Access;
class PermissionPickerWorkflowTest extends TestCase {
 use RefreshDatabase;
 private function user($role='pmo'){return User::create(['name'=>'Role reviewer','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 public function test_project_role_picker_uses_catalog_and_saves_exact_explicit_permissions():void {
  $pmo=$this->user();$this->actingAs($pmo)->get(route('role-directory.index'))->assertOk()->assertDontSee('Permissions Preset')->assertDontSee('Fine-tune')->assertSee('data-permission-picker',false)->assertSee('tasks.download_attachments')->assertSee('project.members_manage');
  $r=$this->postJson(route('roles.quick'),['name'=>'QA reviewer','permissions'=>['tasks.view_all','view_project','tasks.view_all']]);$r->assertOk();$role=ProjectRole::findOrFail($r->json('role.id'));$this->assertSame(['project.view','tasks.view_all'],$role->default_permissions);
  $this->postJson(route('roles.quick'),['name'=>'Invalid','permissions'=>['invented.permission']])->assertUnprocessable();$this->assertFalse(ProjectRole::where('name','Invalid')->exists());
 }
 public function test_project_creator_cannot_grant_company_administration_permissions():void {
  $user=$this->user('user');UserPermission::create(['user_id'=>$user->id,'permission'=>'projects.create','allowed'=>true]);
  $this->actingAs($user)->postJson(route('roles.quick'),['name'=>'Admin escalation','permissions'=>['users.manage']])->assertUnprocessable();
  $this->postJson(route('roles.quick'),['name'=>'Project collaborator','permissions'=>['tasks.create']])->assertOk();
 }
 public function test_directory_creates_role_and_permissions_in_one_submission_and_updates_canonically():void {
  $pmo=$this->user();$this->actingAs($pmo)->post(route('role-directory.store'),['name'=>'Delivery reviewer','permissions'=>['view_project','tasks.view_all']])->assertRedirect();$role=ProjectRole::where('name','Delivery reviewer')->firstOrFail();$this->assertSame(['project.view','tasks.view_all'],$role->default_permissions);
  $this->put(route('role-directory.update',$role),['name'=>'Delivery reviewer','permissions'=>['project.view','tasks.download_attachments']])->assertRedirect();$this->assertSame(['project.view','tasks.download_attachments'],$role->fresh()->default_permissions);
  $this->get(route('role-directory.index',['role'=>$role->id]))->assertOk()->assertSee('data-permission-picker',false)->assertSee('Check a person');
  $this->delete(route('role-directory.delete',$role))->assertRedirect();$this->assertDatabaseMissing('project_roles',['id'=>$role->id]);$this->assertDatabaseHas('activity_logs',['action'=>'role.deleted','subject_id'=>$role->id]);
 }
 public function test_pm_permission_uncheck_blocks_real_actions_and_recheck_restores_them():void {
  $admin=$this->user();$pm=$this->user('user');$role=ProjectRole::create(['name'=>'Project Manager','slug'=>'pm','is_system'=>true,'default_permissions'=>['project.view','tasks.view_all','tasks.create','tasks.edit_all','tasks.update_status']]);
  $p=\App\Models\Project::create(['name'=>'Policy project','code'=>'POL1','created_by'=>$admin->id,'pm_user_id'=>$pm->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
  $t=\App\Models\Task::create(['project_id'=>$p->id,'title'=>'Policy task','code'=>'T1','created_by'=>$admin->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['project.view','tasks.view_all']])->assertRedirect();
  $this->actingAs($pm)->patchJson(route('tasks.update',$t),['status'=>'in_progress'])->assertForbidden();
  $this->postJson(route('tasks.store',$p),[])->assertForbidden();
  $this->assertFalse(app(Access::class)->project($pm,$p,'tasks.create'));
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['project.view','tasks.view_all','tasks.edit_all','tasks.update_status']])->assertRedirect();
  $this->actingAs($pm)->patchJson(route('tasks.update',$t),['status'=>'in_progress'])->assertOk();
  $this->actingAs($admin)->put(route('role-directory.update',$role),[])->assertRedirect();$this->assertSame([],$role->fresh()->default_permissions);$this->assertFalse(app(Access::class)->project($pm,$p,'tasks.view_all'));
 }
 public function test_team_role_can_grant_and_revoke_assigned_task_updates():void {
  $admin=$this->user();$member=$this->user('user');$role=ProjectRole::create(['name'=>'Team Member','slug'=>'team','is_system'=>true,'default_permissions'=>['tasks.view_assigned','tasks.edit_assigned','tasks.update_status']]);
  $p=\App\Models\Project::create(['name'=>'Team policy','code'=>'POL2','created_by'=>$admin->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
  \App\Models\ProjectMembership::create(['project_id'=>$p->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
  $t=\App\Models\Task::create(['project_id'=>$p->id,'title'=>'Assigned','code'=>'T1','created_by'=>$admin->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);$t->assignees()->attach($member->id);
  $this->actingAs($member)->patchJson(route('tasks.update',$t),['status'=>'in_progress'])->assertOk();
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['tasks.view_assigned']])->assertRedirect();
  $this->actingAs($member)->patchJson(route('tasks.update',$t),['status'=>'completed'])->assertForbidden();
 }
}
