<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectInvitation extends Model {
 protected $fillable=['project_id','user_id','status','responded_at','decline_reason'];
 protected function casts(): array {return ['responded_at'=>'datetime'];}
 public function project(){return $this->belongsTo(Project::class);}
 public function user(){return $this->belongsTo(User::class);}
}
