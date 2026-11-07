<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskDelay extends Model
{
    protected $fillable = [
        'task_id', 'reported_by', 'reason_code', 'details', 'responsible_party_type',
        'responsible_party_name', 'action_owner_id', 'revised_expected_end_at', 'status', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['revised_expected_end_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function task() { return $this->belongsTo(Task::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    public function actionOwner() { return $this->belongsTo(User::class, 'action_owner_id'); }
}
