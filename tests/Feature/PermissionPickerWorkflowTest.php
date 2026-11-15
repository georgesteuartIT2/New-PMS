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
  $pmo=$this->user();$this->actingAs($pmo)->get(route('role-directory.index'))->assertOk()->assertDontSee('Permissions Preset')->assertDontSee('Fine-tune')->assertSee('data-permission-picker',false)->assertSee('data-permission-group-count',false)->assertSee('Select the actions people with this role are allowed to perform.')->assertSee('tasks.download_attachments')->assertSee('project.members_manage');
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
 public function test_project_manager_and_owner_defaults_cover_every_project_scoped_permission():void {
  $this->seed();
  $expected=collect(Access::CATALOG)->reject(fn($permission)=>$permission['category']==='company')->keys()->values()->all();
  foreach(['pm','po'] as $slug){
   $role=ProjectRole::where('slug',$slug)->firstOrFail();
   $this->assertEqualsCanonicalizing($expected,$role->default_permissions);
  }
 }
 public function test_owner_permission_uncheck_and_recheck_changes_effective_access():void {
  $admin=$this->user();$owner=$this->user('user');
  $role=ProjectRole::create(['name'=>'Project Owner','slug'=>'po','is_system'=>true,'default_permissions'=>['project.view','tasks.view_all','tasks.create']]);
  $project=\App\Models\Project::create(['name'=>'Owner policy','code'=>'POL3','created_by'=>$admin->id,'owner_user_id'=>$owner->id,'approval_stage'=>'planning','starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
  $this->assertTrue(app(Access::class)->project($owner,$project,'tasks.create'));
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['project.view','tasks.view_all']])->assertRedirect();
  $this->assertFalse(app(Access::class)->project($owner,$project,'tasks.create'));
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['project.view','tasks.view_all','tasks.create']])->assertRedirect();
  $this->assertTrue(app(Access::class)->project($owner,$project,'tasks.create'));
 }
 public function test_sponsor_and_steering_defaults_are_read_only_and_remain_configurable():void {
  $this->seed();
  $viewing=['project.view','project.gantt_view','project.reports_view','project.budget_view','tasks.view_all','tasks.download_attachments','tasks.view_critical_path'];
  foreach(['ps','steering'] as $slug)$this->assertEqualsCanonicalizing($viewing,ProjectRole::where('slug',$slug)->firstOrFail()->default_permissions);
  $admin=$this->user();$member=$this->user('user');$role=ProjectRole::where('slug','ps')->firstOrFail();
  $project=\App\Models\Project::create(['name'=>'Sponsor policy','code'=>'POL4','created_by'=>$admin->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
  \App\Models\ProjectMembership::create(['project_id'=>$project->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
  $this->assertTrue(app(Access::class)->project($member,$project,'project.reports_view'));
  $this->assertFalse(app(Access::class)->project($member,$project,'project.edit'));
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['project.view']])->assertRedirect();
  $this->assertFalse(app(Access::class)->project($member,$project,'project.reports_view'));
  $this->actingAs($admin)->put(route('role-directory.update',$role),['permissions'=>['project.view','project.edit']])->assertRedirect();
  $this->assertTrue(app(Access::class)->project($member,$project,'project.edit'));
 }
 public function test_role_directory_separates_system_and_project_roles():void {
  $admin=$this->user();
  $this->actingAs($admin)->get(route('role-directory.index',['system_role'=>'admin_viewer']))->assertOk()->assertSee('System roles')->assertSee('Project roles')->assertSee('Admin Viewer')->assertSee('Protected System Role')->assertSee('System-managed access');
 }
 public function test_custom_roles_have_inline_delete_action_but_built_in_roles_do_not():void {
  $admin=$this->user();
  $custom=ProjectRole::create(['name'=>'Temporary reviewer','slug'=>'temporary_reviewer','is_system'=>false,'default_permissions'=>['project.view']]);
  $builtIn=ProjectRole::create(['name'=>'Built-in reviewer','slug'=>'built_in_reviewer','is_system'=>true,'default_permissions'=>['project.view']]);
  $page=$this->actingAs($admin)->get(route('role-directory.index',['role'=>$custom->id]));
  $page->assertOk()->assertSee('aria-label="Delete Temporary reviewer"',false)->assertDontSee('aria-label="Delete Built-in reviewer"',false);
 }
 public function test_effective_access_checker_uses_plain_language_summary():void {
  $admin=$this->user();$member=$this->user('user');$role=ProjectRole::create(['name'=>'Viewer','slug'=>'viewer','default_permissions'=>['project.view']]);
  $project=\App\Models\Project::create(['name'=>'Readable project','code'=>'POL5','created_by'=>$admin->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
  \App\Models\ProjectMembership::create(['project_id'=>$project->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
  $this->actingAs($admin)->get(route('role-directory.index',['preview_user_id'=>$member->id,'preview_project_id'=>$project->id]))->assertOk()->assertSee('Check project access')->assertSee('Actions allowed')->assertSee('Actions not allowed')->assertSee('Show access')->assertSee('Not allowed');
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
