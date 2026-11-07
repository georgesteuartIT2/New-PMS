<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectRole extends Model {
 protected $fillable=['name','slug','is_system','default_permissions'];
 public function memberships(){return $this->hasMany(ProjectMembership::class);}
 protected function casts(): array {return ['is_system'=>'boolean','default_permissions'=>'array'];}
}
