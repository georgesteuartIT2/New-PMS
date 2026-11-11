<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','job_title','email','password','system_role','account_status','must_change_password','subsidiary_id','company_name','weekly_capacity_hours'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array {return ['password'=>'hashed','must_change_password'=>'boolean','email_verified_at'=>'datetime','weekly_capacity_hours'=>'integer'];}
 public function isPmo(): bool {return $this->system_role==='pmo';}
 public function isAdminViewer(): bool {return $this->system_role==='admin_viewer';}
 public function canViewPortfolio(): bool {return $this->isPmo() || $this->isAdminViewer();}
 public function systemRoleLabel(): string {return match($this->system_role){'pmo'=>'PMO Admin','admin_viewer'=>'Admin Viewer',default=>'User'};}
 public function isActive(): bool {return $this->account_status==='active';}
 public function subsidiary(){return $this->belongsTo(Subsidiary::class);}
 public function memberships(): HasMany {return $this->hasMany(ProjectMembership::class);}
 public function pmProjects(): HasMany {return $this->hasMany(Project::class, 'pm_user_id');}
 public function assignedTasks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {return $this->belongsToMany(Task::class, 'task_assignments')->withPivot('allocation_percent','assigned_at');}
 public function tasks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {return $this->assignedTasks();}
 public function socialAccounts(): HasMany {return $this->hasMany(SocialAccount::class);}
 public function directPermissions(): HasMany {return $this->hasMany(UserPermission::class);}
 public function assignmentHistoriesAsNew(): HasMany {return $this->hasMany(TaskAssignmentHistory::class, 'new_user_id');}
 public function assignmentHistoriesAsOld(): HasMany {return $this->hasMany(TaskAssignmentHistory::class, 'previous_user_id');}
 public function meetings(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {return $this->belongsToMany(Meeting::class, 'meeting_attendees')->withPivot('response_status')->withTimestamps();}
 public function organizedMeetings(): HasMany {return $this->hasMany(Meeting::class, 'created_by');}
}
