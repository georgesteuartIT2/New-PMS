<?php
namespace App\Services;
use App\Models\{Project,Task,TaskImport,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
class TaskSpreadsheetService {
 public const COLUMNS=['task_code','parent_task_code','task_name','start_date','end_date','start_time','end_time','assigned_user_email','status','progress','priority','depends_on'];
 public function export(Project $project,bool $blank=false):string {
  $book=new Spreadsheet();$sheet=$book->getActiveSheet();
  foreach(self::COLUMNS as $col=>$label)$sheet->setCellValueExplicit([$col+1,1],$label,DataType::TYPE_STRING);
  $row=2;
  if(!$blank){foreach($project->tasks()->with('parent','assignees','dependencies')->orderBy('sort_order')->get() as $task){
   $values=[$task->code,$task->parent?->code,$task->title,$task->starts_at->format('Y-m-d'),$task->ends_at->format('Y-m-d'),$task->starts_at->format('H:i'),$task->ends_at->format('H:i'),$task->assignees->first()?->email,$task->status,$task->progress,$task->priority,$task->dependencies->pluck('code')->implode(',')];
   foreach($values as $i=>$value)$sheet->setCellValueExplicit([$i+1,$row],(string)($value??''),DataType::TYPE_STRING);
   $row++;
  }}
  $sheet->freezePane('A2');$sheet->getStyle('A1:L1')->getFont()->setBold(true);
  $guide=$book->createSheet();$guide->setTitle('Instructions');
  $guideRows=[
   ['GS ProjectFlow - Task Import Guide','Enter data in the first worksheet. Do not rename the column headings.'],
   ['task_code','A stable unique code, e.g. T001'],['parent_task_code','Leave blank for main tasks, or enter another task_code for a subtask'],
   ['task_name','A short description, required'],['start_date / end_date','Use YYYY-MM-DD, e.g. 2026-11-10'],
   ['start_time / end_time','Use 24-hour HH:MM, e.g. 09:00 and 17:00'],
   ['assigned_user_email','Must be an active member of this project, or leave blank'],
   ['status','todo, in_progress, on_hold, completed'],['progress','0 to 100'],
   ['priority','low, medium, high'],['depends_on','Optional comma-separated task codes'],
   ['IMPORTANT','Import first shows a preview. Matching task_code updates; no silent deletion.'],
  ];
  foreach($guideRows as $i=>$values){$guide->setCellValue('A'.($i+1),$values[0]);$guide->setCellValue('B'.($i+1),$values[1]);}
  $guide->getColumnDimension('A')->setWidth(28);$guide->getColumnDimension('B')->setWidth(87);
  $guide->getStyle('A1:B1')->getFont()->setBold(true);
  $book->setActiveSheetIndex(0);
  foreach(range('A','L') as $col)$sheet->getColumnDimension($col)->setAutoSize(true);
  $path=tempnam(sys_get_temp_dir(),'gs_tasks_');
  (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
  return $path;
 }
 public function parse(UploadedFile $file,Project $project):array {
  $ext=strtolower($file->getClientOriginalExtension());
  if(!in_array($ext,['xlsx','csv'],true))throw ValidationException::withMessages(['file'=>'Only .xlsx or .csv files are supported.']);
  try{$book=IOFactory::load($file->getRealPath());$rows=$book->getActiveSheet()->toArray(null,false,true,false);}catch(\Throwable $e){throw ValidationException::withMessages(['file'=>'Unable to read the spreadsheet. Please use the downloaded template.']);}
  if(count($rows)<2)throw ValidationException::withMessages(['file'=>'The spreadsheet has no task rows.']);
  if(count($rows)>501)throw ValidationException::withMessages(['file'=>'Import up to 500 task rows at a time.']);
  $headers=array_map(fn($v)=>strtolower(trim((string)$v)),array_slice($rows[0],0,count(self::COLUMNS)));
  if($headers!==self::COLUMNS)throw ValidationException::withMessages(['file'=>'Spreadsheet columns do not match the template. Download a new template.']);
  $members=$project->memberships()->with('user')->get()->pluck('user.email')->map(fn($e)=>strtolower($e))->all();
  if($project->manager)$members[]=strtolower($project->manager->email);
  $errors=[];$records=[];$codes=[];$existing=$project->tasks()->get()->keyBy('code');
  foreach(array_slice($rows,1) as $index=>$row){
   $line=$index+2;$v=array_combine(self::COLUMNS,array_map(fn($a)=>is_scalar($a)?trim((string)$a):'',array_pad(array_slice($row,0,count(self::COLUMNS)),count(self::COLUMNS),'')));
   if(!array_filter($v,fn($x)=>$x!==''))continue;
   $code=$v['task_code'];$name=$v['task_name'];
   if($code===''||!preg_match('/^[A-Za-z0-9_-]{1,60}$/',$code))$errors[]="Row $line: invalid Task Code.";
   if(isset($codes[$code]))$errors[]="Row $line: duplicate Task Code.";$codes[$code]=true;
   if($name===''||mb_strlen($name)>200)$errors[]="Row $line: Task Name is required (max 200 characters).";
   foreach(['start_date','end_date'] as $field){
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v[$field])||!$this->validDate($v[$field]))$errors[]="Row $line: $field must use YYYY-MM-DD.";
   }
   foreach(['start_time','end_time'] as $field){if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$v[$field]))$errors[]="Row $line: $field must use HH:MM (24-hour).";}
   if($this->validDate($v['start_date'])&&$this->validDate($v['end_date'])&&preg_match('/^\d{2}:\d{2}$/',$v['start_time'])&&preg_match('/^\d{2}:\d{2}$/',$v['end_time'])){
    if(strtotime($v['end_date'].' '.$v['end_time'])<=strtotime($v['start_date'].' '.$v['start_time']))$errors[]="Row $line: end date/time must be later than start.";
   }
   if(!in_array($v['status'],['todo','in_progress','on_hold','completed'],true))$errors[]="Row $line: invalid status.";
   if(!preg_match('/^\d{1,3}$/',$v['progress'])||(int)$v['progress']>100)$errors[]="Row $line: progress must be 0 to 100.";
   if(!in_array($v['priority'],['low','medium','high'],true))$errors[]="Row $line: priority must be low, medium or high.";
   if($v['assigned_user_email']!==''&&!in_array(strtolower($v['assigned_user_email']),$members,true))$errors[]="Row $line: assignee must be an existing project member.";
   if($v['parent_task_code']===$code&&$code!=='')$errors[]="Row $line: a task cannot be its own parent.";
   $records[]=$v;
  }
  if(!$records)$errors[]='No valid task rows found.';
  foreach($records as $v){
   if($v['parent_task_code']!==''&&!isset($codes[$v['parent_task_code']])&&!$existing->has($v['parent_task_code']))$errors[]='Task '.$v['task_code'].': parent code was not found.';
   foreach(array_filter(array_map('trim',explode(',',$v['depends_on']))) as $dep){
    if($dep===$v['task_code']||(!isset($codes[$dep])&&!$existing->has($dep)))$errors[]='Task '.$v['task_code'].': invalid dependency '.$dep.'.';
   }
  }
  // Block parent cycles including rows updated in this import.
  $parents=[];foreach($existing as $code=>$task)$parents[$code]=$task->parent?->code??'';
  foreach($records as $v)$parents[$v['task_code']]=$v['parent_task_code'];
  foreach($parents as $code=>$unused){$visited=[];$cur=$code;
   while(!empty($parents[$cur])){if(isset($visited[$cur])){$errors[]='Circular parent relationship involving '.$code.'.';break;}$visited[$cur]=true;$cur=$parents[$cur];}
  }
  // Reject dependency cycles as well as parent cycles, including references to stored tasks.
  $depGraph=[];
  foreach($existing as $code=>$task)$depGraph[$code]=$task->dependencies()->pluck('code')->all();
  foreach($records as $v)$depGraph[$v['task_code']]=array_values(array_filter(array_map('trim',explode(',',$v['depends_on']))));
  foreach(array_keys($depGraph) as $code){
   $walk=function($node,$stack)use(&$walk,$depGraph){if(isset($stack[$node]))return true;$stack[$node]=true;
    foreach($depGraph[$node]??[] as $other)if($walk($other,$stack))return true;
    return false;
   };
   if($walk($code,[])){$errors[]='Circular task dependency involving '.$code.'.';break;}
  }
  if($errors)throw ValidationException::withMessages(['file'=>array_slice(array_unique($errors),0,30)]);
  return $records;
 }
 private function validDate(string $date):bool {if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return false;[$y,$m,$d]=array_map('intval',explode('-',$date));return checkdate($m,$d,$y);}
 public function apply(Project $project,User $actor,array $records,string $filename,ProjectService $projects):array {
  return DB::transaction(function()use($project,$actor,$records,$filename,$projects){
   $created=0;$updated=0;$map=$project->tasks()->get()->keyBy('code');
   foreach($records as $v){
    $fields=['title'=>$v['task_name'],'starts_at'=>$v['start_date'].' '.$v['start_time'].':00','ends_at'=>$v['end_date'].' '.$v['end_time'].':00','status'=>$v['status'],'progress'=>$v['status']==='completed'?100:(int)$v['progress'],'priority'=>$v['priority']];
    if(isset($map[$v['task_code']])){$task=$map[$v['task_code']];$task->update($fields);$updated++;}
    else{$task=Task::create(['project_id'=>$project->id,'code'=>$v['task_code'],...$fields,'created_by'=>$actor->id,'sort_order'=>$project->tasks()->count()+1]);$created++;$map[$v['task_code']]=$task;}
   }
   foreach($records as $v){
    $task=$map[$v['task_code']];$parent=$v['parent_task_code']!==''?$map[$v['parent_task_code']]??null:null;
    $task->update(['parent_task_id'=>$parent?->id]);
    $email=strtolower($v['assigned_user_email']);
    if($email!==''){$user=User::where('email',$email)->firstOrFail();$task->assignees()->sync([$user->id]);}
    else $task->assignees()->detach();
    $deps=array_filter(array_map('trim',explode(',',$v['depends_on'])));
    $task->dependencies()->sync(array_map(fn($code)=>$map[$code]->id,$deps));
   }
   TaskImport::create(['project_id'=>$project->id,'user_id'=>$actor->id,'file_name'=>$filename,'created_count'=>$created,'updated_count'=>$updated,'status'=>'completed','details'=>['rows'=>count($records)]]);
   $projects->recalculate($project);
   return ['created'=>$created,'updated'=>$updated];
  });
 }
}
