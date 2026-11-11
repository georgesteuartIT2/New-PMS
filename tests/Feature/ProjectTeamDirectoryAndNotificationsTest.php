<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Project,ProjectRole,ProjectTemplate};
use App\Services\{AzureGraphService,ProjectService};
use Illuminate\Support\Facades\DB;
class ProjectTeamDirectoryAndNotificationsTest extends TestCase {
 use RefreshDatabase;
 private function person($role='user'){return User::create(['name'=>'Directory person','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 private function project($manager){return Project::create(['name'=>'Team project','code'=>uniqid('QA-'),'created_by'=>$manager->id,'pm_user_id'=>$manager->id,'status'=>'active','starts_on'=>today(),'ends_on'=>today()->addWeek()]);}
 public function test_manager_can_import_directory_person_but_outsider_cannot_and_repeat_import_is_idempotent():void {
  $manager=$this->person();$project=$this->project($manager);$outside=$this->person();$id='12345678-1234-4234-8234-123456789abc';config(['services.azure.tenant'=>'test-tenant']);
  $remote=['id'=>$id,'displayName'=>'New colleague','mail'=>'new@example.test','userPrincipalName'=>'new@example.test','accountEnabled'=>true];
  $this->mock(AzureGraphService::class,function($mock)use($remote){$mock->shouldReceive('configured')->andReturn(true);$mock->shouldReceive('search')->with('New')->once()->andReturn([$remote]);$mock->shouldReceive('get')->twice()->andReturn($remote);});
  $this->actingAs($outside)->getJson(route('users.azure',['q'=>'New','directory_project_id'=>$project->id]))->assertForbidden();
  $this->postJson(route('users.azure.add'),['azure_id'=>$id,'directory_project_id'=>$project->id])->assertForbidden();
  $this->actingAs($manager)->getJson(route('users.azure',['q'=>'New','directory_project_id'=>$project->id]))->assertOk()->assertJsonPath('results.0.displayName','New colleague');
  $this->postJson(route('users.azure.add'),['azure_id'=>$id,'directory_project_id'=>$project->id])->assertOk()->assertJsonPath('user.email','new@example.test');
  $this->postJson(route('users.azure.add'),['azure_id'=>$id,'directory_project_id'=>$project->id])->assertOk();$this->assertSame(1,User::where('email','new@example.test')->count());
  $role=ProjectRole::create(['name'=>'Team member','slug'=>'team']);$new=User::where('email','new@example.test')->firstOrFail();
  $this->post(route('projects.members.store',$project),['user_id'=>$new->id,'project_role_id'=>$role->id])->assertRedirect(route('projects.show',$project).'#team');$this->assertDatabaseHas('project_memberships',['project_id'=>$project->id,'user_id'=>$new->id]);
  $project->update(['status'=>'archived']);$this->getJson(route('users.azure',['q'=>'New','directory_project_id'=>$project->id]))->assertForbidden();
 }
 public function test_project_template_cannot_be_changed_by_edit_request():void {
  $admin=$this->person('pmo');$project=$this->project($admin);$template=ProjectTemplate::create(['name'=>'Original plan','created_by'=>$admin->id,'status'=>'active']);$project->update(['project_template_id'=>$template->id]);
  $data=['name'=>'Changed name','starts_on'=>today()->toDateString(),'ends_on'=>today()->addWeek()->toDateString(),'status'=>'active','project_template_id'=>null];
  $this->actingAs($admin)->putJson(route('projects.update',$project),$data)->assertUnprocessable()->assertJsonValidationErrors('project_template_id');$this->assertSame($template->id,$project->fresh()->project_template_id);
  unset($data['project_template_id']);$this->putJson(route('projects.update',$project),$data)->assertOk();$this->assertSame($template->id,$project->fresh()->project_template_id);
  $this->get(route('projects.show',$project))->assertOk()->assertSee('Original project template')->assertDontSee('name="project_template_id"',false)->assertSee('data-directory-picker',false);
 }
 public function test_pmo_creation_delivers_pm_in_app_request_without_running_mail_queue_and_drafts_do_not():void {
  config(['queue.default'=>'database']);$admin=$this->person('pmo');$manager=$this->person();ProjectRole::create(['name'=>'Project Manager','slug'=>'pm']);
  $data=['name'=>'Approval delivery','starts_on'=>today(),'ends_on'=>today()->addWeek(),'pm_user_id'=>$manager->id];
  $project=app(ProjectService::class)->create($data,$admin);
  $notification=$manager->notifications()->first();$this->assertNotNull($notification);$this->assertSame('project_approval',$notification->data['type']);$this->assertSame($project->id,$notification->data['project_id']);$this->assertSame('/projects/'.$project->id,$notification->data['url']);$this->assertNull($notification->read_at);$this->assertSame(1,DB::table('jobs')->count());$this->assertSame(0,$admin->notifications()->count());
  app(ProjectService::class)->create($data+['status_override'=>'draft'],$admin);$this->assertSame(1,$manager->notifications()->count());
  $r=$this->actingAs($manager)->get(route('notifications.index'));$r->assertOk()->assertSee('Project approval requested')->assertSee('Review project')->assertSee('Mark as read');
  $html=preg_replace('#(href|src)="http://localhost/(css|js)/#','$1="file:///'.str_replace('\\','/',base_path()).'/public/$2/',$r->getContent());file_put_contents(storage_path('app/ui-review/notification-request-fixture.html'),$html);
  $this->post(route('notifications.read',$notification->id))->assertRedirect(route('projects.show',$project));$this->assertSame(0,$manager->unreadNotifications()->count());
  $this->actingAs($admin)->post(route('notifications.read',$notification->id))->assertNotFound();
 }
}
