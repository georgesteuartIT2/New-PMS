<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Project extends Model {
 protected $fillable=['code','name','description','starts_on','ends_on','status','progress','project_template_id','pm_user_id','subsidiary_id','created_by','baseline_starts_on','baseline_ends_on','baseline_captured_at'];
 protected function casts(): array {return ['starts_on'=>'date','ends_on'=>'date','progress'=>'integer','baseline_starts_on'=>'date','baseline_ends_on'=>'date','baseline_captured_at'=>'datetime'];}
 public function subsidiary(){return $this->belongsTo(Subsidiary::class);}
 public function manager(){return $this->belongsTo(User::class,'pm_user_id');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
 public function template(){return $this->belongsTo(ProjectTemplate::class,'project_template_id');}
 public function memberships(){return $this->hasMany(ProjectMembership::class);}
 public function tasks(){return $this->hasMany(Task::class);}
 public function invitations(){return $this->hasMany(ProjectInvitation::class);}
}
