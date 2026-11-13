<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Project,ProjectRole,ProjectMembership,ProjectInvitation,ProjectTemplate,Task,ApplicationSetting};
use App\Services\{Access,ProjectService,EmailConfigurationService};
use Illuminate\Support\Facades\{DB,Notification,Mail};
use App\Notifications\{ProjectActivatedNotification,ProjectAssignmentNotification};
class AdminViewerAndEmailSettingsTest extends TestCase {
 use RefreshDatabase;
 private function person($role='user'){return User::create(['name'=>'Reviewer','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 private function project($user,$status='active'){return Project::create(['name'=>'Company project','code'=>uniqid('QA-'),'pm_user_id'=>$user->id,'created_by'=>$user->id,'status'=>$status,'starts_on'=>today(),'ends_on'=>today()->addWeek()]);}
 private function snapshot($name,$response){$html=preg_replace('#(href|src)="http://(?:localhost|127\.0\.0\.1:8000)/(css|js)/#','$1="file:///'.str_replace('\\','/',base_path()).'/public/$2/',$response->getContent());file_put_contents(storage_path('app/ui-review/'.$name.'.html'),$html);}
 public function test_viewer_can_see_the_whole_portfolio_without_mutation_controls():void {
  $pmo=$this->person('pmo');$viewer=$this->person('admin_viewer');$project=$this->project($pmo);$draft=$this->project($pmo,'draft');$task=Task::create(['project_id'=>$project->id,'code'=>'T1','title'=>'Visible task','created_by'=>$pmo->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);$template=ProjectTemplate::create(['name'=>'Viewer template','created_by'=>$pmo->id,'status'=>'active']);
  foreach(['dashboard'=>route('dashboard'),'projects'=>route('projects.index'),'project'=>route('projects.show',$project),'draft'=>route('projects.show',$draft),'tasks'=>route('pmo.task-monitor'),'team'=>route('pmo.team-monitor'),'calendar'=>route('calendar.index'),'people'=>route('users.index'),'person'=>route('users.show',$pmo),'templates'=>route('templates.index'),'template'=>route('templates.show',$template),'activity'=>route('activity.index'),'reports'=>route('reports.index'),'gantt'=>route('gantt.portfolio')] as $name=>$url){$r=$this->actingAs($viewer)->get($url);$r->assertOk();$this->snapshot('viewer-'.$name,$r);}
  $this->get(route('dashboard'))->assertViewHas('isPmo',true)->assertDontSee('+ New project')->assertDontSee('@else')->assertDontSee('My tasks &rarr;',false)->assertSee('Read-only portfolio access');
  $this->get(route('projects.show',$project))->assertSee('Visible task')->assertDontSee('class="quick-status-select"',false)->assertDontSee('data-show-dialog="project-edit-dialog"',false);
  $this->get(route('users.index'))->assertDontSee('title="Edit User"',false)->assertDontSee('Add person');
  $access=app(Access::class);$this->assertTrue($access->project($viewer,$draft));$this->assertFalse($access->project($viewer,$project,'edit_project'));$this->assertFalse($access->task($viewer,$task,'update'));$this->assertFalse($access->hasCompanyPermission($viewer,'users.manage'));
 }
 public function test_viewer_cannot_write_even_via_direct_routes_or_permission_grants():void {
  $pmo=$this->person('pmo');$viewer=$this->person('admin_viewer');$project=$this->project($pmo);$role=ProjectRole::create(['name'=>'Team','slug'=>'team']);$task=Task::create(['project_id'=>$project->id,'code'=>'T1','title'=>'Original','created_by'=>$pmo->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);
  \App\Models\UserPermission::create(['user_id'=>$viewer->id,'permission'=>'users.manage','allowed'=>true,'granted_by'=>$pmo->id]);
  $this->assertFalse(app(Access::class)->hasCompanyPermission($viewer,'users.manage'));
  $this->actingAs($viewer);
  foreach([['POST',route('projects.store')],['PUT',route('projects.update',$project)],['DELETE',route('projects.destroy',$project)],['POST',route('projects.members.store',$project)],['POST',route('projects.respond',$project)],['PATCH',route('tasks.update',$task)],['POST',route('tasks.assign',$task)],['PATCH',route('users.update',$pmo)],['PUT',route('settings.email.update')],['POST',route('settings.email.test')],['POST',route('users.azure.add')]] as [$method,$url])$this->json($method,$url,[])->assertForbidden();
  foreach(['settings.system','projects.create','administration.index','role-directory.index'] as $route)$this->get(route($route))->assertForbidden();
  $this->assertSame('Original',$task->fresh()->title);$this->assertSame(0,$task->fresh()->progress);$this->assertSame('active',$project->fresh()->status);
 }
 public function test_pmo_can_save_encrypted_email_settings_without_exposing_or_overwriting_password():void {
  $pmo=$this->person('pmo');$data=['mailer'=>'smtp','host'=>'smtp.example.test','port'=>587,'scheme'=>'smtp','username'=>'mailer','password'=>'super-secret-password','from_address'=>'projects@example.test','from_name'=>'ProjectFlow'];
  $this->actingAs($pmo)->put(route('settings.email.update'),$data)->assertRedirect();$raw=DB::table('application_settings')->where('key','email')->value('value');$this->assertStringNotContainsString('super-secret-password',$raw);$this->assertSame('super-secret-password',ApplicationSetting::find('email')->value['password']);
  $r=$this->get(route('settings.system'));$r->assertOk()->assertSee('Email configuration')->assertDontSee('super-secret-password');$this->snapshot('email-settings',$r);
  $data['password']='';$this->put(route('settings.email.update'),$data)->assertRedirect();$this->assertSame('super-secret-password',app(EmailConfigurationService::class)->current()['password']);$this->assertSame('smtp.example.test',config('mail.mailers.smtp.host'));$this->assertSame('projects@example.test',config('mail.from.address'));
  $data['clear_password']=1;$this->put(route('settings.email.update'),$data)->assertRedirect();$this->assertNull(app(EmailConfigurationService::class)->current()['password']);
  $this->actingAs($this->person())->get(route('settings.system'))->assertForbidden();
 }
 public function test_only_active_team_members_are_notified_once_after_pm_accepts():void {
  Notification::fake();$pmo=$this->person('pmo');$pm=$this->person();$member=$this->person();$inactive=$this->person();$inactive->update(['account_status'=>'inactive']);$outsider=$this->person();$viewer=$this->person('admin_viewer');$pmRole=ProjectRole::create(['name'=>'PM','slug'=>'pm']);$teamRole=ProjectRole::create(['name'=>'Team','slug'=>'team']);
  $project=app(ProjectService::class)->create(['name'=>'Acceptance email','starts_on'=>today(),'ends_on'=>today()->addWeek(),'pm_user_id'=>$pm->id,'members'=>[['user_id'=>$member->id,'project_role_id'=>$teamRole->id]]],$pmo);Notification::assertSentTo($pm,ProjectAssignmentNotification::class);Notification::assertNotSentTo($member,ProjectActivatedNotification::class);
  foreach([$inactive,$viewer] as $u)ProjectMembership::create(['project_id'=>$project->id,'user_id'=>$u->id,'project_role_id'=>$teamRole->id]);
  $this->actingAs($pm)->post(route('projects.respond',$project),['decision'=>'accept'])->assertRedirect();Notification::assertSentToTimes($member,ProjectActivatedNotification::class,1);Notification::assertNotSentTo([$pm,$inactive,$outsider,$viewer],ProjectActivatedNotification::class);
  $this->post(route('projects.respond',$project),['decision'=>'accept'])->assertNotFound();Notification::assertSentToTimes($member,ProjectActivatedNotification::class,1);
 }

 public function test_cannot_delete_or_demote_the_last_pmo_admin(): void
 {
  // Ensure user 1 exists as non-PMO root user
  User::firstOrCreate(['id' => 1], ['name' => 'Root', 'email' => 'root@example.test', 'password' => 'secret', 'system_role' => 'user']);
  User::where('system_role', 'pmo')->delete();

  $singlePmo = $this->person('pmo');
  $otherPmo = $this->person('pmo');

  $this->actingAs($otherPmo)->deleteJson(route('users.destroy', $singlePmo))->assertOk();
  $this->actingAs($otherPmo)->deleteJson(route('users.destroy', $otherPmo))->assertForbidden();
  $this->actingAs($otherPmo)->patchJson(route('users.update', $otherPmo), ['system_role' => 'user'])->assertForbidden();
  $this->actingAs($otherPmo)->patchJson(route('users.update', $otherPmo), ['account_status' => 'inactive'])->assertForbidden();
 }
 public function test_professional_project_emails_render_and_stale_invites_are_skipped():void {
  $pmo=$this->person('pmo');$pm=$this->person();$member=$this->person();$role=ProjectRole::create(['name'=>'PM','slug'=>'pm']);$team=ProjectRole::create(['name'=>'Team','slug'=>'team']);Notification::fake();
  $p=app(ProjectService::class)->create(['name'=>'Mail <review>','starts_on'=>today(),'ends_on'=>today()->addWeek(),'pm_user_id'=>$pm->id,'members'=>[['user_id'=>$member->id,'project_role_id'=>$team->id]]],$pmo);
  $invite=new ProjectAssignmentNotification($p);$this->assertTrue($invite->shouldSend($pm,'mail'));$message=$invite->toMail($pm);$html=view($message->view['html'],$message->viewData)->render();$this->assertStringContainsString('Review project assignment',$html);$this->assertStringContainsString('Mail &lt;review&gt;',$html);
  $p->update(['status'=>'active']);$this->assertFalse($invite->shouldSend($pm,'mail'));
  $activated=new ProjectActivatedNotification($p->fresh());$this->assertTrue($activated->shouldSend($member,'mail'));$this->assertFalse($activated->shouldSend($pm,'mail'));$message=$activated->toMail($member);$this->assertStringContainsString('Open project',view($message->view['html'],$message->viewData)->render());
 }

 public function test_notification_queue_status_and_retry_are_pmo_only_and_queue_scoped():void {
  $pmo=$this->person('pmo');$user=$this->person();$uuid=(string)\Illuminate\Support\Str::uuid();
  DB::table('failed_jobs')->insert(['uuid'=>$uuid,'connection'=>'database','queue'=>'project-notifications','payload'=>'{}','exception'=>'Private transport diagnostic','failed_at'=>now()]);
  $page=$this->actingAs($pmo)->get(route('settings.system'))->assertOk()->assertSee('1 failed')->assertDontSee('Private transport diagnostic');$this->snapshot('email-queue-status',$page);
  $this->actingAs($user)->post(route('settings.email.retry'),['uuid'=>$uuid])->assertForbidden();
  $this->actingAs($pmo)->post(route('settings.email.retry'),['uuid'=>(string)\Illuminate\Support\Str::uuid()])->assertNotFound();
  \Illuminate\Support\Facades\Artisan::shouldReceive('call')->once()->with('queue:retry',['id'=>[$uuid]])->andReturn(0);
  $this->post(route('settings.email.retry'),['uuid'=>$uuid])->assertRedirect();
 }
}
