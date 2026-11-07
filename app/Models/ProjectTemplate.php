<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Services\TemplateScheduleService;

class ProjectTemplate extends Model {
    protected $fillable = [
        'name',
        'description',
        'category',
        'estimated_duration',
        'duration_unit',
        'reference_start_date',
        'working_hours',
        'planning_periods',
        'status',
        'created_by'
    ];

    protected $casts = [
        'reference_start_date' => 'date',
        'estimated_duration' => 'integer',
        'planning_periods' => 'array',
    ];

    public function tasks(): HasMany {
        return $this->hasMany(TemplateTask::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query) {
        return $query->where('status', 'archived');
    }

    public function isArchived(): bool {
        return $this->status === 'archived';
    }

    public function formattedDuration(): string {
        $unit = match($this->duration_unit) {
            'working_days' => 'working days',
            'days' => 'days',
            'weeks' => 'weeks',
            'months' => 'months',
            default => 'working days'
        };
        return ($this->estimated_duration ?: 14) . ' ' . $unit;
    }

    public function schedule(?string $startDate = null): array {
        $start = $startDate ?: ($this->reference_start_date ? $this->reference_start_date->format('Y-m-d') : null);
        return app(TemplateScheduleService::class)->calculate($this, $start);
    }

    public function duplicate(User $actor): ProjectTemplate {
        return DB::transaction(function() use ($actor) {
            $clone = $this->replicate(['created_at', 'updated_at']);
            $clone->name = $this->name . ' (Copy)';
            $clone->created_by = $actor->id;
            $clone->status = 'active';
            $clone->save();

            foreach ($this->tasks()->orderBy('sort_order')->get() as $task) {
                $newTask = $task->replicate(['created_at', 'updated_at']);
                $newTask->project_template_id = $clone->id;
                $newTask->save();
            }

            return $clone;
        });
    }
}
