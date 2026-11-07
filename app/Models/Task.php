<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Task extends Model {
 protected $fillable=[
     'project_id','parent_task_id','code','title','description','starts_at','ends_at',
     'baseline_starts_at','baseline_ends_at','status','progress','last_progress_updated_at',
     'priority','estimated_hours','sort_order','created_by'
 ];
 protected function casts(): array{
     return [
         'starts_at'=>'datetime',
         'ends_at'=>'datetime',
         'progress'=>'integer',
         'baseline_starts_at'=>'datetime',
         'baseline_ends_at'=>'datetime',
         'last_progress_updated_at'=>'datetime',
         'estimated_hours'=>'float',
     ];
 }
 public function isOverdue($asOf = null): bool {
     $asOf = $asOf ?? now();
     return $this->ends_at !== null
         && $this->ends_at->lessThan($asOf)
         && (int) $this->progress < 100
         && $this->status !== 'completed';
 }
 public function getIsOverdueAttribute(): bool {
     return $this->isOverdue();
 }
 public function project(){return $this->belongsTo(Project::class);}
 public function parent(){return $this->belongsTo(Task::class,'parent_task_id');}
 public function children(){return $this->hasMany(Task::class,'parent_task_id');}
 public function assignees(){return $this->belongsToMany(User::class,'task_assignments')->withPivot('allocation_percent','assigned_at');}
 public function attachments(){return $this->hasMany(TaskAttachment::class);}
 public function comments(){return $this->hasMany(TaskComment::class);}
 public function delayReports(){return $this->hasMany(TaskDelay::class)->latest();}
 public function openDelay(){return $this->hasOne(TaskDelay::class)->where('status','open')->latestOfMany();}
 public function dependencies(){return $this->belongsToMany(Task::class,'task_dependencies','task_id','depends_on_task_id');}
 public function assignmentHistories(){return $this->hasMany(TaskAssignmentHistory::class)->latest('created_at');}
 public function updateRequests(){return $this->hasMany(TaskUpdateRequest::class)->latest();}
}
