<?php
namespace App\Services;
use Illuminate\Support\Collection;
class TaskTree {
 public static function treeOrdered(Collection $tasks): Collection {
  $group=$tasks->groupBy(fn($t)=>(string)($t->parent_task_id??0));
  $ordered=collect();$seen=[];
  $walk=function($pid)use(&$walk,&$ordered,&$seen,$group){
   $children=($group->get((string)$pid)??collect())->sortBy([['sort_order','asc'],['id','asc']])->values();
   foreach($children as $task){
    if(isset($seen[$task->id]))continue;$seen[$task->id]=true;
    $ordered->push($task);
    $walk($task->id);
   }
  };
  $walk(0);
  foreach($tasks as $task){
   if(!isset($seen[$task->id])){
    $ordered->push($task);
   }
  }
  return $ordered;
 }
 /** Attach project-wide numbering before pagination/filtering can change its meaning. */
 public static function attachNumbers(Collection $tasks):Collection {
  if($tasks->isEmpty())return $tasks;
  $rows=\App\Models\Task::whereIn('project_id',$tasks->pluck('project_id')->unique())->get(['id','project_id','parent_task_id','sort_order']);
  $numbers=[];foreach($rows->groupBy('project_id') as $projectTasks)$numbers+=self::numbers($projectTasks);
  foreach($tasks as $task)$task->setAttribute('display_number',$numbers[$task->id]??'?');
  return $tasks;
 }
 public static function numbers(Collection $tasks):array {
  $group=$tasks->groupBy(fn($t)=>(string)($t->parent_task_id??0));
  $result=[];$seen=[];
  $walk=function($pid,$prefix='')use(&$walk,&$result,&$seen,$group){
   foreach(($group->get((string)$pid)??collect())->sortBy([['sort_order','asc'],['id','asc']])->values() as $i=>$task){
    if(isset($seen[$task->id]))continue;$seen[$task->id]=true;
    $number=$prefix.($i+1);$result[$task->id]=$number;$walk($task->id,$number.'.');
   }
  };
  $walk(0);foreach($tasks as $task)if(!isset($result[$task->id])){$result[$task->id]='?';}
  return $result;
 }
}
