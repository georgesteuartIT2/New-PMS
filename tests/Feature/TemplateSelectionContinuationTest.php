<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,ProjectTemplate,TemplateTask,ProjectRole};
use App\Services\ProjectService;
use Illuminate\Support\Facades\Notification;
class TemplateSelectionContinuationTest extends TestCase {
 use RefreshDatabase;
 private function user($role='pmo'){return User::create(['name'=>'Template reviewer','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 public function test_selection_opens_editable_plan_and_saved_plan_returns_to_the_same_project_flow():void {
  $pmo=$this->user();$template=ProjectTemplate::create(['name'=>'Selected plan','category'=>'General','estimated_duration'=>10,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09','working_hours'=>'09:00 - 17:00','status'=>'active','created_by'=>$pmo->id]);TemplateTask::create(['project_template_id'=>$template->id,'code'=>'T1','title'=>'Original task','start_date'=>'2026-11-09','end_date'=>'2026-11-09','start_time'=>'09:00','end_time'=>'17:00','sort_order'=>0]);
  $flow='12345678-1234-4234-8234-123456789abc';
  $r=$this->actingAs($pmo)->get(route('templates.index',['select_for_project'=>1,'project_flow'=>$flow,'draft'=>77]));$r->assertOk()->assertSee('data-select-template="'.$template->id.'"',false)->assertSee('openTemplatePlan('.$template->id.", 'select')",false)->assertSee('Original task')->assertSee($flow);
  $html=preg_replace('#(href|src)="http://(?:localhost|127\.0\.0\.1:8000)/(css|js)/#','$1="file:///'.str_replace('\\','/',base_path()).'/public/$2/',$r->getContent());file_put_contents(storage_path('app/ui-review/template-select-flow.html'),$html);
  $assignee=$this->user('user');
  $data=['select_for_project'=>1,'project_flow'=>$flow,'draft'=>77,'name'=>'Selected plan','category'=>'General','estimated_duration'=>10,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09','working_hours'=>'09:00 - 17:00','tasks'=>[['code'=>'T1','title'=>'Updated selected task','start_date'=>'2026-11-09','end_date'=>'2026-11-09','start_time'=>'09:00','end_time'=>'17:00','assignee_id'=>$assignee->id]]];
  $updated=$this->postJson(route('templates.store'),$data);$updated->assertOk()->assertJsonPath('success',true);$copyId=$updated->json('template_id');$this->assertNotEquals($template->id,$copyId);$this->assertSame('Selected plan (Copy)',ProjectTemplate::findOrFail($copyId)->name);$this->assertSame($assignee->id,ProjectTemplate::findOrFail($copyId)->tasks()->first()->assignee_user_id);$updated->assertJsonPath('redirect',route('projects.create',['project_flow'=>$flow,'draft'=>77,'template_id'=>$copyId,'mode'=>'template']));$this->assertSame(2,ProjectTemplate::count());$this->assertSame('Original task',$template->tasks()->first()->title);
  Notification::fake();$pm=$this->user('user');ProjectRole::create(['name'=>'Project Manager','slug'=>'pm']);$project=app(ProjectService::class)->create(['name'=>'Final project','project_template_id'=>$copyId,'starts_on'=>'2026-11-16','ends_on'=>'2026-11-27','pm_user_id'=>$pm->id],$pmo);$this->assertSame($copyId,$project->project_template_id);$this->assertSame('Updated selected task',$project->tasks()->first()->title);$this->assertTrue($project->tasks()->first()->assignees()->whereKey($assignee->id)->exists());$this->assertTrue($project->memberships()->where('user_id',$assignee->id)->exists());
  unset($data['select_for_project'],$data['project_flow'],$data['draft'],$data['tasks'][0]['assignee_id']);$this->putJson(route('templates.update',$template),$data)->assertOk()->assertJsonPath('redirect',route('templates.index'));
 }
 public function test_unprivileged_user_cannot_save_edits_to_a_shared_template():void {
  $pmo=$this->user();$template=ProjectTemplate::create(['name'=>'Protected','created_by'=>$pmo->id,'status'=>'active']);$this->actingAs($this->user('user'))->putJson(route('templates.update',$template),['name'=>'Changed'])->assertForbidden();$this->assertSame('Protected',$template->fresh()->name);
 }
 public function test_project_setup_saves_customized_template_to_project_draft_without_creating_a_copy():void {
  $pmo=$this->user();$project=\App\Models\Project::create(['name'=>'Excel setup','code'=>'EXCEL-SETUP','created_by'=>$pmo->id,'pm_user_id'=>$pmo->id,'status'=>'active','approval_stage'=>'planning','starts_on'=>today(),'ends_on'=>today()->addWeek()]);
  $template=ProjectTemplate::create(['name'=>'Excel plan','category'=>'General','estimated_duration'=>5,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09','status'=>'active','created_by'=>$pmo->id]);
  TemplateTask::create(['project_template_id'=>$template->id,'code'=>'T1','title'=>'Original task','start_date'=>'2026-11-09','end_date'=>'2026-11-09','start_time'=>'09:00','end_time'=>'17:00','sort_order'=>0]);
  $assignee=$this->user('user');
  $data=['select_for_project'=>1,'save_as_project_plan'=>1,'project_template_id'=>$template->id,'plan_project'=>$project->id,'starts_on'=>'2026-11-16','ends_on'=>'2026-11-27','name'=>'Excel plan','category'=>'General','estimated_duration'=>5,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09','working_hours'=>'09:00 - 17:00','tasks'=>[['code'=>'T1','title'=>'Imported task','duration_days'=>1,'start_date'=>'2026-11-16','end_date'=>'2026-11-16','start_time'=>'09:00','end_time'=>'17:00','assignee_id'=>$assignee->id]]];
  $saved=$this->actingAs($pmo)->postJson(route('templates.store'),$data)->assertOk()->assertJsonPath('success',true);
  $saved->assertJsonPath('message',"Customized tasks from 'Excel plan' saved to this project's draft. The original template was not changed.");
  $saved->assertJsonPath('redirect',route('projects.plan.setup',['starts_on'=>'2026-11-16','ends_on'=>'2026-11-27','project'=>$project->id,'template_id'=>$template->id]));
  $this->assertSame(1,ProjectTemplate::count());
  $this->assertSame(0,$project->tasks()->count());
  $this->assertSame('Original task',$template->tasks()->first()->title);
  $savedDraft=$project->fresh()->setup_draft;
  $this->assertSame('template',$savedDraft['mode']);
  $this->assertSame($template->id,$savedDraft['project_template_id']);
  $draft=json_decode($savedDraft['blank_plan'],true);
  $this->assertSame('Imported task',$draft['tasks'][0]['title']);
  $this->assertSame($assignee->id,$draft['tasks'][0]['assignee_id']);
  $this->assertNotNull($project->fresh()->setup_draft_saved_at);
  $this->get(route('projects.plan.setup',['project'=>$project->id,'template_id'=>$template->id]))->assertOk()->assertSee('Excel plan')->assertSee('Selected')->assertSee('Imported task')->assertSee('Project team')->assertSee('Optional')->assertSee('Add team members');

  $this->actingAs($pmo)->post(route('projects.plan.initialize',$project),[
   'mode'=>'template','project_template_id'=>$template->id,'blank_plan'=>json_encode($draft),'starts_on'=>'2026-11-16','ends_on'=>'2026-11-27','publish_now'=>'1',
  ])->assertRedirect(route('projects.show',$project).'#tasks');
  $this->assertSame($template->id,$project->fresh()->project_template_id);
  $this->assertSame('Imported task',$project->tasks()->first()->title);
  $this->assertTrue($project->tasks()->first()->assignees()->whereKey($assignee->id)->exists());
 }
}
