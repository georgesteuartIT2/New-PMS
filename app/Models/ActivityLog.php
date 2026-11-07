<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model {
 protected $fillable=['actor_id','action','subject_type','subject_id','details'];
 public function actor(){return $this->belongsTo(User::class,'actor_id');}
 protected function casts(): array{return ['details'=>'array'];}
}
