<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskAssignmentHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'previous_user_id',
        'new_user_id',
        'changed_by',
        'action',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function previousUser()
    {
        return $this->belongsTo(User::class, 'previous_user_id');
    }

    public function newUser()
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

