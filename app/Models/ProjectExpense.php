<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectExpense extends Model {protected $fillable=['receipt_path','receipt_name','amount','description','spent_on','created_by'];protected function casts():array{return ['amount'=>'decimal:2','spent_on'=>'date'];}public function project(){return $this->belongsTo(Project::class);}public function creator(){return $this->belongsTo(User::class,'created_by');}}
