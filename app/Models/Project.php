<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Project extends Model {
 protected $fillable=['setup_draft','plan_assignment_drafts','owner_user_id','approval_stage','plan_initialized','plan_published_at','approval_reason','planned_budget','code','name','description','starts_on','ends_on','status','progress','project_template_id','pm_user_id','subsidiary_id','created_by','baseline_starts_on','baseline_ends_on','baseline_captured_at'];
 protected function casts(): array {return ['setup_draft'=>'array','plan_assignment_drafts'=>'array','plan_initialized'=>'boolean','plan_published_at'=>'datetime','planned_budget'=>'decimal:2','starts_on'=>'date','ends_on'=>'date','progress'=>'integer','baseline_starts_on'=>'date','baseline_ends_on'=>'date','baseline_captured_at'=>'datetime'];}
 public function expenses(){return $this->hasMany(ProjectExpense::class);}
 public function canViewBudget(User $user):bool {return $user->canViewPortfolio() || app(\App\Services\Access::class)->project($user,$this,'project.budget_view') || $this->canManageBudget($user);}
 public function canManageBudget(User $user):bool {return !$user->isAdminViewer() && ($user->isPmo() || app(\App\Services\Access::class)->project($user,$this,'project.budget_manage'));}
 public function getActualCostAttribute(){return $this->expenses_sum_amount ?? $this->expenses()->sum('amount');}
 public function subsidiary(){return $this->belongsTo(Subsidiary::class);}
 public function owner(){return $this->belongsTo(User::class,'owner_user_id');}
 public function manager(){return $this->belongsTo(User::class,'pm_user_id');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
 public function template(){return $this->belongsTo(ProjectTemplate::class,'project_template_id');}
 public function memberships(){return $this->hasMany(ProjectMembership::class);}
 public function tasks(){return $this->hasMany(Task::class);}
 public function invitations(){return $this->hasMany(ProjectInvitation::class);}
 public function meetings(){return $this->hasMany(Meeting::class);}
}
