<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateTask extends Model {
    protected $fillable = [
        'project_template_id',
        'code',
        'parent_code',
        'title',
        'description',
        'duration_days',
        'priority',
        'sort_order',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'dependency_code',
        'suggested_role_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    public function suggestedRole(): BelongsTo {
        return $this->belongsTo(ProjectRole::class, 'suggested_role_id');
    }

    public function isSubtask(): bool {
        return !empty($this->parent_code);
    }
}
