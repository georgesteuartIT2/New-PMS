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
  $data=['select_for_project'=>1,'project_flow'=>$flow,'draft'=>77,'name'=>'Selected plan','category'=>'General','estimated_duration'=>10,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09','working_hours'=>'09:00 - 17:00','tasks'=>[['code'=>'T1','title'=>'Updated selected task','start_date'=>'2026-11-09','end_date'=>'2026-11-09','start_time'=>'09:00','end_time'=>'17:00']]];
  $updated=$this->putJson(route('templates.update',$template),$data);$updated->assertOk()->assertJsonPath('success',true)->assertJsonPath('template_id',$template->id)->assertJsonPath('redirect',route('projects.create',['project_flow'=>$flow,'draft'=>77,'template_id'=>$template->id,'mode'=>'template']));$this->assertSame(1,ProjectTemplate::count());$this->assertSame('Updated selected task',$template->tasks()->first()->title);
  Notification::fake();$pm=$this->user('user');ProjectRole::create(['name'=>'Project Manager','slug'=>'pm']);$project=app(ProjectService::class)->create(['name'=>'Final project','project_template_id'=>$template->id,'starts_on'=>'2026-11-16','ends_on'=>'2026-11-27','pm_user_id'=>$pm->id],$pmo);$this->assertSame($template->id,$project->project_template_id);$this->assertSame('Updated selected task',$project->tasks()->first()->title);
  unset($data['select_for_project'],$data['project_flow'],$data['draft']);$this->putJson(route('templates.update',$template),$data)->assertOk()->assertJsonPath('redirect',route('templates.index'));
 }
 public function test_unprivileged_user_cannot_save_edits_to_a_shared_template():void {
  $pmo=$this->user();$template=ProjectTemplate::create(['name'=>'Protected','created_by'=>$pmo->id,'status'=>'active']);$this->actingAs($this->user('user'))->putJson(route('templates.update',$template),['name'=>'Changed'])->assertForbidden();$this->assertSame('Protected',$template->fresh()->name);
 }
}
