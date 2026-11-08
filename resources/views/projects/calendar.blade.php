@extends('layouts.app')
@section('title', 'Calendar · ' . $project->name)

@section('content')
@include('projects._tabs')
<div class="page-head" style="margin-bottom: 20px;">
    <div>
        <div class="breadcrumb-trail">
            <a href="{{ route('projects.index') }}">Projects</a>
            <span class="crumb-sep">›</span>
            <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
            <span class="crumb-sep">›</span>
            <span>Calendar</span>
        </div>
        <div class="title-row" style="margin-top: 6px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0;">Project Calendar &amp; Schedule</h1>
        </div>
        <p class="muted" style="margin-top: 4px; font-size: 13.5px;">
            {{ $project->name }} ({{ $project->code }}) · Manage tasks and project meetings synced to Microsoft Outlook.
        </p>
    </div>

    <div class="inline-actions" style="display: flex; gap: 8px; align-items: center;">
        <a href="{{ route('projects.show', $project) }}" class="btn btn-outline" style="font-weight: 600;">
            ← Project Workspace
        </a>
        @if($canManageMeetings)
            <button type="button" class="btn btn-primary" data-show-dialog="schedule-meeting-dialog" style="background: #315F58; border-color: #315F58; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                + Schedule Meeting
            </button>
        @endif
    </div>
</div>

<!-- Calendar Workspace Container -->
<div class="calendar-workspace-card">
    <!-- Top Bar: Month Navigation & Legend -->
    <div class="calendar-topbar">
        <!-- Month Navigator -->
        <div class="calendar-month-nav">
            <a href="{{ route('projects.calendar', ['project' => $project, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-outline btn-sm" aria-label="Previous month" style="font-weight: 700;">
                ‹
            </a>
            <span class="calendar-month-title">{{ $month->format('F Y') }}</span>
            <a href="{{ route('projects.calendar', ['project' => $project, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-outline btn-sm" aria-label="Next month" style="font-weight: 700;">
                ›
            </a>
            @if(!$month->isCurrentMonth())
                <a href="{{ route('projects.calendar', ['project' => $project, 'month' => now()->format('Y-m')]) }}" class="btn btn-outline btn-sm" style="font-size: 11px; margin-left: 4px;">
                    Today
                </a>
            @endif
        </div>

        <!-- Legend / Info Pills -->
        <div style="display: flex; align-items: center; gap: 14px; font-size: 12px;">
            <span style="display: inline-flex; align-items: center; gap: 4px; color: #334155;">
                <span style="width: 8px; height: 8px; border-radius: 2px; background: #315F58;"></span> Task
            </span>
            <span style="display: inline-flex; align-items: center; gap: 4px; color: #DC2626; font-weight: 600;">
                <span style="width: 8px; height: 8px; border-radius: 2px; background: #DC2626;"></span> Overdue Task
            </span>
            <span style="display: inline-flex; align-items: center; gap: 4px; color: #4338CA; font-weight: 600;">
                <span style="width: 8px; height: 8px; border-radius: 2px; background: #4F46E5;"></span> Outlook Meeting
            </span>
        </div>
    </div>

    <!-- Calendar Grid Weekday Header (Mon - Sun) -->
    <div class="calendar-grid-header">
        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
            <div class="calendar-weekday-cell">{{ $dayName }}</div>
        @endforeach
    </div>

    <!-- Calendar Grid Body (Month Cells) -->
    <div class="calendar-grid-body">
        @foreach($cells as $cell)
            @php
                $dayDate = $cell['date'];
                $isOffMonth = !$cell['is_current_month'];
                $isToday = $cell['is_today'];
                $cellTasks = $cell['tasks'];
                $cellMeetings = $cell['meetings'];
            @endphp
            <div class="calendar-day-cell {{ $isOffMonth ? 'off-month' : '' }} {{ $isToday ? 'is-today' : '' }}" data-cell-date="{{ $dayDate->format('Y-m-d') }}" data-has-events="{{ ($cellTasks->count() + $cellMeetings->count()) > 0 ? 'true' : 'false' }}">
                <!-- Day Number & Quick Action -->
                <div class="calendar-day-top">
                    <span class="calendar-day-num"><span class="calendar-day-number">{{ $dayDate->day }}</span><span class="calendar-agenda-date">{{ $dayDate->format('D, d M') }}</span></span>
                    @if($canManageMeetings && !$isOffMonth)
                        <button type="button" class="cal-more-btn" data-schedule-meeting data-prefill-date="{{ $dayDate->format('Y-m-d') }}" title="Schedule meeting on this day" style="font-size: 13px; line-height: 1; padding: 0 2px; color: #94A3B8;">
                            +
                        </button>
                    @endif
                </div>

                <!-- Event Stack -->
                <div class="calendar-event-stack">
                    <!-- 1. Project Meetings -->
                    @foreach($cellMeetings as $meeting)
                        @php
                            $canManageThisMeeting = auth()->user()->isPmo() || 
                                $meeting->created_by === auth()->id() || 
                                $project->pm_user_id === auth()->id();

                            $meetingData = [
                                'id' => $meeting->id,
                                'title' => $meeting->title,
                                'description' => $meeting->description,
                                'project_name' => $project->name,
                                'starts_at' => $meeting->starts_at->format('d M Y, h:i A'),
                                'ends_at' => $meeting->ends_at->format('h:i A'),
                                'formatted_datetime' => $meeting->starts_at->format('d M Y') . ' · ' . $meeting->starts_at->format('h:i A') . ' - ' . $meeting->ends_at->format('h:i A'),
                                'location' => $meeting->location ?: 'Microsoft Teams Meeting',
                                'organizer_name' => $meeting->creator?->name ?? 'Organizer',
                                'ms_sync_status' => $meeting->ms_sync_status,
                                'outlook_web_link' => $meeting->outlook_web_link,
                                'attendees' => $meeting->attendeeRecords->map(fn($a) => ['name' => $a->name, 'email' => $a->email]),
                                'can_manage' => $canManageThisMeeting,
                            ];
                        @endphp
                        <button type="button" 
                                class="cal-item cal-item-meeting {{ $meeting->ms_sync_status === 'synced' ? 'is-synced' : '' }}" 
                                data-meeting-info="{{ json_encode($meetingData) }}"
                                title="Meeting: {{ $meeting->title }} ({{ $meeting->starts_at->format('h:i A') }}) - Click to view">
                            <span style="font-size: 10px; font-weight: 700; opacity: 0.85;">{{ $meeting->starts_at->format('H:i') }}</span>
                            {{ $meeting->title }}
                        </button>
                    @endforeach

                    <!-- 2. Project Tasks -->
                    @foreach($cellTasks as $task)
                        @php
                            $isOverdue = $task->isOverdue();
                        @endphp
                        <a href="{{ route('tasks.show', $task) }}" data-task-panel="{{ route('tasks.panel', $task) }}" class="cal-item cal-item-task {{ $isOverdue ? 'is-overdue' : '' }}"
                           title="Task: {{ $task->title }} - Progress: {{ $task->progress }}%">
                            @if($isOverdue)
                                <span style="color: #DC2626; font-weight: 700;">[Overdue]</span>
                            @endif
                            {{ $task->title }}
                        </a>
                    @endforeach

                    <!-- More count -->
                    @php
                        $hiddenCount = max(0, ($cellMeetings->count() + $cellTasks->count()) - 3);
                    @endphp
                    @if($hiddenCount > 0)
                        <button type="button" class="cal-more-btn" data-calendar-more aria-expanded="false">+{{ $hiddenCount }} more</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Include Shared Meeting Scheduling & View Modals -->
@include('calendar._meeting_modals')
@include('tasks._drawer')

<script src="{{ asset('js/calendar-meetings.js') }}"></script>
@endsection
