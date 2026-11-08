<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Meeting extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'description',
        'location',
        'meeting_link',
        'starts_at',
        'ends_at',
        'status',
        'ms_event_id',
        'ms_synced_at',
        'ms_sync_status',
        'ms_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ms_synced_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organizer(): BelongsTo
    {
        return $this->creator();
    }

    public function attendeeRecords(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_attendees')
            ->withPivot('email', 'name', 'response_status')
            ->withTimestamps();
    }

    public function isSyncedToOutlook(): bool
    {
        return $this->ms_sync_status === 'synced' && filled($this->ms_event_id);
    }

    public function getOutlookWebLinkAttribute(): string
    {
        $subject = urlencode($this->title);
        $body = urlencode(($this->description ? $this->description . "\n\n" : "") . "Project: " . ($this->project?->name ?? ''));
        $start = $this->starts_at->toIso8601String();
        $end = $this->ends_at->toIso8601String();
        $location = urlencode($this->location ?: 'Online Meeting');
        $attendees = urlencode(implode(';', $this->attendeeRecords->pluck('email')->all()));

        return "https://outlook.office.com/calendar/0/deeplink/compose?subject={$subject}&body={$body}&startdt={$start}&enddt={$end}&location={$location}&to={$attendees}";
    }
}

