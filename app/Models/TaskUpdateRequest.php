<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskUpdateRequest extends Model
{
    protected $fillable = [
        'task_id',
        'requested_by',
        'target_user_id',
        'message',
        'status',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}

