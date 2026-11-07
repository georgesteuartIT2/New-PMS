<?php
namespace App\Services;
use Illuminate\Support\Collection;
class TaskTree {
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
