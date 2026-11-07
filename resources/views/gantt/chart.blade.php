@extends('layouts.app')
@section('title', $title)
@section('content')
<!-- Screen 7: Gantt Chart -->
<div class="page-head">
    <div>
        @if($project)
            <div class="breadcrumb-trail">
                <a href="{{ route('projects.index') }}">Projects</a>
                <span class="crumb-sep">›</span>
                <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                <span class="crumb-sep">›</span>
                <span>Gantt Chart</span>
            </div>
            <div class="title-row">
                <h1>{{ $project->name }}</h1>
                <span class="pill {{ $project->status }}">{{ ucfirst($project->status) }}</span>
            </div>
        @else
            <h1>{{ $title }}</h1>
        @endif
        <p class="muted">{{ $subtitle }}. Visual schedule and task progression over time.</p>
    </div>
</div>



@php
    $min = $items->count() ? \Carbon\Carbon::parse($items->min('starts_at'))->startOfWeek() : now()->startOfWeek();
    $max = $items->count() ? \Carbon\Carbon::parse($items->max('ends_at'))->endOfWeek() : now()->addWeeks(6);
    $days = max(14, min(730, $min->diffInDays($max) + 1));
    $weeks = (int)ceil($days / 7);
    $width = max(720, $weeks * 110);
@endphp

@if($project && $health)
<div class="schedule-hint">
    <b>Schedule health: {{ ucwords(str_replace('_',' ',$health['health'])) }}</b>
    · {{ $health['overdue_tasks']->count() }} overdue tasks
    · {{ $health['open_delays']->count() }} reported blockers.
    @if($health['cpm_warning']) {{ $health['cpm_warning'] }}
    @elseif($health['cpm_reliable']) Critical tasks are marked below (zero float based on entered dependencies). @endif
    <a href="{{ route('projects.show',$project) }}#work-plan" class="text-action">Open interactive workspace →</a>
</div>
@endif
<div class="card gantt-card">
    <div class="card-head" style="margin-bottom: 14px;">
        <div class="gantt-legend" style="display: flex; gap: 18px; font-size: 12px; color: var(--muted); flex-wrap:wrap;">
            <span style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 14px; height: 10px; background: #C9D9D4; border-radius: 2px;"></span>
                Planned duration
            </span>
            <span style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 14px; height: 10px; background: var(--primary); border-radius: 2px;"></span>
                Completed Progress
            </span>
            @if($health && $health['cpm_reliable'])<span><span style="display:inline-block;width:12px;height:10px;background:#BA756E;margin-right:5px"></span> Critical path</span>@endif
        </div>
    </div>

    <div class="table-wrap" style="border: 1px solid var(--border); border-radius: var(--radius);">
        <div style="display: grid; grid-template-columns: 340px 1fr; min-width: 900px;">
            <!-- Left Info Table -->
            <div style="border-right: 1px solid var(--border); background: #FFFFFF;">
                <div style="height: 48px; background: #F9FAFB; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 40px 1.4fr 1fr 1fr; align-items: center; padding: 0 12px; font-size: 11.5px; font-weight: 700; color: #6B7280; text-transform: uppercase;">
                    <span>#</span>
                    <span>{{ $project ? 'Task' : 'Project' }}</span>
                    <span>Start</span>
                    <span>End</span>
                </div>
                @forelse($items as $idx => $item)
                    @php
                        $start = \Carbon\Carbon::parse($item->starts_at);
                        $end = \Carbon\Carbon::parse($item->ends_at);
                    @endphp
                    <div style="height: 48px; border-bottom: 1px solid var(--border-light); display: grid; grid-template-columns: 40px 1.4fr 1fr 1fr; align-items: center; padding: 0 12px; font-size: 12.5px;">
                        <span class="muted" style="font-size: 11px;">{{ $idx + 1 }}</span>
                        <a href="{{ $item->url }}" class="strong-link" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px;" title="{{ $item->name }}">
                            {{ $item->name }}
                        </a>
                        <span class="muted" style="font-size: 11.5px;">{{ $start->format('d M') }}</span>
                        <span class="muted" style="font-size: 11.5px;">{{ $end->format('d M') }}</span>
                    </div>
                @empty
                    <div class="empty" style="padding: 24px;">No timeline items</div>
                @endforelse
            </div>

            <!-- Right Visual Timeline Scroller -->
            <div style="overflow-x: auto; background: #FAFBFC;">
                <div style="width: {{ $width }}px;">
                    <!-- Timeline header weeks -->
                    <div style="height: 48px; background: #F9FAFB; border-bottom: 1px solid var(--border); display: flex;">
                        @for($w = 0; $w < $weeks; $w++)
                            @php
                                $wStart = $min->copy()->addDays($w * 7);
                            @endphp
                            <div style="flex: 1; border-right: 1px solid var(--border); padding: 8px; font-size: 11px; color: var(--muted); text-align: center;">
                                <div style="font-weight: 650; color: var(--text);">W{{ $w + 1 }}</div>
                                <div>{{ $wStart->format('d M') }}</div>
                            </div>
                        @endfor
                    </div>

                    <!-- Timeline task duration bars -->
                    @forelse($items as $item)
                        @php
                            $start = \Carbon\Carbon::parse($item->starts_at);
                            $end = \Carbon\Carbon::parse($item->ends_at);
                            $leftPercent = max(0, $min->diffInDays($start, false)) / $days * 100;
                            $durationDays = max(1, $start->diffInDays($end) + 1);
                            $barWidthPercent = min(100 - $leftPercent, $durationDays / $days * 100);
                        @endphp
                        <div style="height: 48px; border-bottom: 1px solid var(--border-light); position: relative; display: flex; align-items: center;">
                            <div style="position: absolute; left: {{ $leftPercent }}%; width: {{ max(1.5, $barWidthPercent) }}%; height: 22px; background: {{ !empty($item->critical) ? '#EDC9C6' : '#BFD3CC' }}; border-radius: 4px; overflow: hidden; display: flex;" title="{{ $item->name }} · {{ $item->progress }}%">
                                <div style="background: {{ !empty($item->critical)?'#A94C45':'var(--primary)' }}; height: 100%; width: {{ $item->progress }}%;"></div>
                            </div>
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <p class="help-note mt-16">Drag horizontally to inspect schedules. Click any task name to view its full details.</p>
</div>
@endsection
