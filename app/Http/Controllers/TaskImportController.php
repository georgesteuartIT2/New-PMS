<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\{Access,TaskSpreadsheetService,ProjectService};
use Illuminate\Http\Request;
class TaskImportController {
 private function permit(Request $request,Project $project,Access $access):void {abort_unless($access->canManageTask($request->user(),$project),403);abort_if($project->status==='archived',403);}
 public function export(Request $request,Project $project,Access $access,TaskSpreadsheetService $spreadsheets){
  $this->permit($request,$project,$access);
  $starter=$request->boolean('starter');$fromTemplate=$starter && $project->template!==null;
  $blank=$request->boolean('blank') || ($starter && !$fromTemplate);
  $file=$spreadsheets->export($project,$blank,$fromTemplate);
  $suffix=$starter?($fromTemplate?'-project-template.xlsx':'-blank-template.xlsx'):'-tasks.xlsx';
  return response()->download($file,$project->code.$suffix)->deleteFileAfterSend(true);
 }
 public function preview(Request $request,Project $project,Access $access,TaskSpreadsheetService $spreadsheets){
  $this->permit($request,$project,$access);
  $data=$request->validate(['file'=>'required|file|max:2048|mimes:xlsx,csv,txt']);
  $records=$spreadsheets->parse($data['file'],$project);
  $request->session()->put('task_import_'.$project->id,['records'=>$records,'file_name'=>$data['file']->getClientOriginalName(),'at'=>now()->timestamp]);
  return redirect()->route('tasks.import.review',$project);
 }
 public function review(Request $request,Project $project,Access $access){
  $this->permit($request,$project,$access);
  $preview=$request->session()->get('task_import_'.$project->id);
  if(!$preview)return redirect()->route('projects.show',$project)->withErrors(['file'=>'Choose a file first.']);
  return view('tasks.import-review',compact('project','preview'));
 }
 public function confirm(Request $request,Project $project,Access $access,TaskSpreadsheetService $spreadsheets,ProjectService $projects){
  $this->permit($request,$project,$access);
  $preview=$request->session()->get('task_import_'.$project->id);
  abort_unless($preview&&now()->timestamp-$preview['at']<1800,422,'Import preview expired. Please upload again.');
  $result=$spreadsheets->apply($project,$request->user(),$preview['records'],$preview['file_name'],$projects);
  $request->session()->forget('task_import_'.$project->id);
  return redirect()->route('projects.show',$project)->with('success',"Imported: {$result['created']} new tasks, {$result['updated']} updated tasks.");
 }
}
