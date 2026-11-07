<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectRolePermission extends Model {
 protected $fillable=['project_id','project_role_id','permission','allowed'];
 protected function casts(): array{return ['allowed'=>'boolean'];}
}
