<?php
namespace App\Services;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
class Audit {
 public static function record(string $action,Model $subject,array $details=[]):void {
  ActivityLog::create(['actor_id'=>auth()->id(),'action'=>$action,'subject_type'=>class_basename($subject),'subject_id'=>$subject->getKey(),'details'=>$details]);
 }
}
