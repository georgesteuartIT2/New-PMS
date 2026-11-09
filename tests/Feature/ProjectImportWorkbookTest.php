<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Models\{User,Project,ProjectTemplate,TemplateTask,Task};
use App\Services\TaskSpreadsheetService;
use PhpOffice\PhpSpreadsheet\IOFactory;
class ProjectImportWorkbookTest extends TestCase {
 use RefreshDatabase;
 private function setupProject():array{
  $u=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'secret123','system_role'=>'pmo','account_status'=>'active','must_change_password'=>false]);
  $template=ProjectTemplate::create(['name'=>'Original plan','category'=>'General','estimated_duration'=>5,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09','working_hours'=>'09:00 - 17:00','status'=>'active','created_by'=>$u->id]);
  TemplateTask::create(['project_template_id'=>$template->id,'code'=>'T1','title'=>'Original task','start_date'=>'2026-11-09','end_date'=>'2026-11-09','start_time'=>'09:00','end_time'=>'17:00','duration_days'=>1,'priority'=>'medium','sort_order'=>1]);
  TemplateTask::create(['project_template_id'=>$template->id,'code'=>'T2','parent_code'=>'T1','dependency_code'=>'T1','title'=>'Original subtask','start_date'=>'2026-11-10','end_date'=>'2026-11-10','start_time'=>'09:00','end_time'=>'17:00','duration_days'=>1,'priority'=>'high','sort_order'=>2]);
  $p=Project::create(['name'=>'Project','code'=>'TEST-001','starts_on'=>'2026-11-16','ends_on'=>'2026-11-20','created_by'=>$u->id,'pm_user_id'=>$u->id,'project_template_id'=>$template->id,'status'=>'active']);
  Task::create(['project_id'=>$p->id,'code'=>'T1','title'=>'Edited current task','starts_at'=>'2026-11-16 09:00','ends_at'=>'2026-11-16 17:00','created_by'=>$u->id,'status'=>'in_progress','progress'=>40]);return [$u,$p];
 }
 public function test_selected_template_download_uses_original_plan_project_dates_and_roundtrips():void{
  [$u,$p]=$this->setupProject();$service=app(TaskSpreadsheetService::class);$file=$service->export($p,false,true);
  try{$book=IOFactory::load($file);$rows=$book->getActiveSheet()->toArray();$this->assertSame(TaskSpreadsheetService::COLUMNS,$rows[0]);$this->assertSame('Original task',$rows[1][2]);$this->assertSame('2026-11-16',$rows[1][3]);$this->assertSame('T1',$rows[2][1]);$this->assertSame('T1',$rows[2][11]);$book->disconnectWorksheets();$records=$service->parse(new UploadedFile($file,'template.xlsx',null,null,true),$p);$this->assertCount(2,$records);$this->assertSame('Original subtask',$records[1]['task_name']);$this->assertSame(40,$p->tasks()->first()->progress);}finally{unlink($file);}
 }
 public function test_blank_and_current_task_exports_remain_separate():void{
  [$u,$p]=$this->setupProject();foreach([true,false] as $blank){$file=app(TaskSpreadsheetService::class)->export($p,$blank);try{$book=IOFactory::load($file);$rows=$book->getActiveSheet()->toArray();$this->assertCount($blank?1:2,$rows);if(!$blank)$this->assertSame('Edited current task',$rows[1][2]);$book->disconnectWorksheets();}finally{unlink($file);}}
 }
 public function test_starter_endpoint_and_dialog_follow_project_setup():void{
  [$u,$p]=$this->setupProject();$this->actingAs($u);$view=$this->get(route('projects.show',$p));$view->assertOk()->assertSee('Download project template')->assertSee('Original plan')->assertSee('Preview changes');
  $r=$this->get(route('tasks.export',[$p,'starter'=>1]));$r->assertDownload('TEST-001-project-template.xlsx');unlink($r->baseResponse->getFile()->getPathname());
  $p->update(['project_template_id'=>null]);$p->unsetRelation('template');$this->get(route('projects.show',$p))->assertOk()->assertSee('Download blank template');
  $r=$this->get(route('tasks.export',[$p,'starter'=>1]));$r->assertDownload('TEST-001-blank-template.xlsx');$file=$r->baseResponse->getFile()->getPathname();try{$book=IOFactory::load($file);$this->assertSame(1,$book->getActiveSheet()->getHighestRow());$book->disconnectWorksheets();}finally{unlink($file);}
 }
}
