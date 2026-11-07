<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TaskImport extends Model {
 protected $fillable=['project_id','user_id','file_name','created_count','updated_count','status','details'];
 protected function casts(): array{return ['details'=>'array'];}
}
