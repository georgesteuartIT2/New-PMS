<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Subsidiary extends Model {
    protected $fillable = [
        'name',
        'code',
        'azure_company_name',
        'description',
        'project_counter',
        'status',
        'created_by'
    ];

    public function projects(): HasMany {
        return $this->hasMany(Project::class);
    }

    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Preview the next sequential project ID for this subsidiary without incrementing the counter.
     */
    public function previewProjectCode(): string {
        $prefix = strtoupper(trim($this->code));
        $count = $this->projects()->count() + 1;
        $counter = max($this->project_counter + 1, $count);
        $candidate = $prefix . '-PRJ-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        while (Project::where('code', $candidate)->exists()) {
            $counter++;
            $candidate = $prefix . '-PRJ-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }

    /**
     * Generate the next sequential project ID for this subsidiary.
     * E.g. GST-PRJ-001, GST-PRJ-002, etc.
     */
    public function nextProjectCode(): string {
        $prefix = strtoupper(trim($this->code));
        $count = $this->projects()->count() + 1;
        $counter = max($this->project_counter + 1, $count);
        $this->update(['project_counter' => $counter]);
        
        $candidate = $prefix . '-PRJ-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        while (Project::where('code', $candidate)->exists()) {
            $counter++;
            $this->update(['project_counter' => $counter]);
            $candidate = $prefix . '-PRJ-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }
}

