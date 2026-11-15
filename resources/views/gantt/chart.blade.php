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
    $scale = $scale ?? request('scale', 'week');
    if (!in_array($scale, ['day', 'week', 'month'], true)) {
        $scale = 'week';
    }

    $validItems = $items->filter(fn($i) => !empty($i->starts_at) && !empty($i->ends_at));
    $minRaw = $validItems->count() ? \Carbon\Carbon::parse($validItems->min('starts_at')) : now();
    $maxRaw = $validItems->count() ? \Carbon\Carbon::parse($validItems->max('ends_at')) : now()->addWeeks(6);

    // Initial PHP calculations for server render
    if ($scale === 'day') {
        $min = $minRaw->copy()->startOfDay();
        $max = $maxRaw->copy()->endOfDay();
        $totalDays = max(14, min(180, $min->diffInDays($max) + 1));
        $colWidth = 46;
        $totalWidth = max(720, $totalDays * $colWidth);
    } elseif ($scale === 'month') {
        $min = $minRaw->copy()->startOfMonth();
        $max = $maxRaw->copy()->endOfMonth();
        $totalMonths = max(3, ($max->year - $min->year) * 12 + $max->month - $min->month + 1);
        $colWidth = 150;
        $totalWidth = max(720, $totalMonths * $colWidth);
        $totalDays = max(30, $min->diffInDays($max) + 1);
    } else { // week (default)
        $min = $minRaw->copy()->startOfWeek();
        $max = $maxRaw->copy()->endOfWeek();
        $totalDays = max(14, min(730, $min->diffInDays($max) + 1));
        $weeks = (int)ceil($totalDays / 7);
        $colWidth = 110;
        $totalWidth = max(720, $weeks * $colWidth);
    }

    $itemsJson = $items->map(function($item) {
        $start = $item->starts_at ? \Carbon\Carbon::parse($item->starts_at)->format('Y-m-d') : null;
        $end = $item->ends_at ? \Carbon\Carbon::parse($item->ends_at)->format('Y-m-d') : null;
        return [
            'id' => $item->id,
            'name' => $item->name,
            'start' => $start,
            'end' => $end,
            'start_formatted' => $start ? \Carbon\Carbon::parse($start)->format('d M') : '—',
            'end_formatted' => $end ? \Carbon\Carbon::parse($end)->format('d M') : '—',
            'progress' => (int) ($item->progress ?? 0),
            'status' => $item->status ?? 'active',
            'critical' => !empty($item->critical),
            'url' => $item->url ?? '#',
        ];
    })->values();
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
    <div class="card-head" style="margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <!-- Left: Legend -->
        <div class="gantt-legend" style="display: flex; gap: 18px; font-size: 12px; color: var(--muted); flex-wrap:wrap; align-items: center;">
            <span style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 14px; height: 10px; background: #C9D9D4; border-radius: 2px;"></span>
                Planned duration
            </span>
            <span style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 14px; height: 10px; background: var(--primary); border-radius: 2px;"></span>
                Completed Progress
            </span>
            @if($health && $health['cpm_reliable'])
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 12px; height: 10px; background: #BA756E; border-radius: 2px;"></span>
                    Critical path
                </span>
            @endif
        </div>

        <!-- Right: Scale Filter (Day, Week, Month) -->
        <div class="view-scale-segmented" id="portfolio-gantt-scale" role="group" aria-label="Timeline scale">
            <button type="button" class="view-scale-btn {{ $scale === 'day' ? 'active' : '' }}" data-scale="day">Day</button>
            <button type="button" class="view-scale-btn {{ $scale === 'week' ? 'active' : '' }}" data-scale="week">Week</button>
            <button type="button" class="view-scale-btn {{ $scale === 'month' ? 'active' : '' }}" data-scale="month">Month</button>
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
                        $start = $item->starts_at ? \Carbon\Carbon::parse($item->starts_at) : null;
                        $end = $item->ends_at ? \Carbon\Carbon::parse($item->ends_at) : null;
                    @endphp
                    <div style="height: 48px; border-bottom: 1px solid var(--border-light); display: grid; grid-template-columns: 40px 1.4fr 1fr 1fr; align-items: center; padding: 0 12px; font-size: 12.5px;">
                        <span class="muted" style="font-size: 11px;">{{ $idx + 1 }}</span>
                        <a href="{{ $item->url }}" class="strong-link" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px;" title="{{ $item->name }}">
                            {{ $item->name }}
                        </a>
                        <span class="muted" style="font-size: 11.5px;">{{ $start ? $start->format('d M') : '—' }}</span>
                        <span class="muted" style="font-size: 11.5px;">{{ $end ? $end->format('d M') : '—' }}</span>
                    </div>
                @empty
                    <div class="empty" style="padding: 24px;">No timeline items</div>
                @endforelse
            </div>

            <!-- Right Visual Timeline Scroller -->
            <div style="overflow-x: auto; background: #FAFBFC;" id="gantt-scroll-viewport">
                <div id="gantt-timeline-canvas" style="width: {{ $totalWidth }}px; min-width: 100%;">
                    <!-- Timeline Header Row -->
                    <div id="gantt-header-row" style="height: 48px; background: #F9FAFB; border-bottom: 1px solid var(--border); display: flex;">
                        @if($scale === 'day')
                            @for($d = 0; $d < $totalDays; $d++)
                                @php
                                    $dayDate = $min->copy()->addDays($d);
                                    $isWeekend = $dayDate->isWeekend();
                                    $isToday = $dayDate->isToday();
                                @endphp
                                <div style="flex: 0 0 {{ $colWidth }}px; width: {{ $colWidth }}px; border-right: 1px solid var(--border); padding: 6px 2px; font-size: 11px; text-align: center; background: {{ $isToday ? '#FEF3C7' : ($isWeekend ? '#F8FAFC' : 'transparent') }};" title="{{ $dayDate->format('D, d M Y') }}">
                                    <div style="font-weight: 700; color: {{ $isToday ? '#92400E' : 'var(--text)' }};">{{ $dayDate->format('d') }}</div>
                                    <div style="font-size: 10px; color: var(--muted);">{{ $dayDate->format('M') }}</div>
                                </div>
                            @endfor
                        @elseif($scale === 'month')
                            @for($m = 0; $m < $totalMonths; $m++)
                                @php
                                    $mDate = $min->copy()->addMonths($m);
                                @endphp
                                <div style="flex: 0 0 {{ $colWidth }}px; width: {{ $colWidth }}px; border-right: 1px solid var(--border); padding: 12px 8px; font-size: 12px; text-align: center; font-weight: 700; color: var(--text);">
                                    {{ $mDate->format('M Y') }}
                                </div>
                            @endfor
                        @else
                            @for($w = 0; $w < $weeks; $w++)
                                @php
                                    $wStart = $min->copy()->addDays($w * 7);
                                @endphp
                                <div style="flex: 0 0 {{ $colWidth }}px; width: {{ $colWidth }}px; border-right: 1px solid var(--border); padding: 8px; font-size: 11px; color: var(--muted); text-align: center;">
                                    <div style="font-weight: 650; color: var(--text);">W{{ $w + 1 }}</div>
                                    <div>{{ $wStart->format('d M') }}</div>
                                </div>
                            @endfor
                        @endif
                    </div>

                    <!-- Timeline Task / Project Bars -->
                    <div id="gantt-bars-container">
                        @forelse($items as $item)
                            @php
                                $start = $item->starts_at ? \Carbon\Carbon::parse($item->starts_at) : null;
                                $end = $item->ends_at ? \Carbon\Carbon::parse($item->ends_at) : null;
                                if ($start && $end) {
                                    if ($scale === 'day') {
                                        $leftPx = max(0, $min->diffInDays($start, false)) * $colWidth;
                                        $durationDays = max(1, $start->diffInDays($end) + 1);
                                        $barWidthPx = max(16, $durationDays * $colWidth);
                                        $style = "left: {$leftPx}px; width: {$barWidthPx}px;";
                                    } else {
                                        $leftPercent = max(0, $min->diffInDays($start, false)) / $totalDays * 100;
                                        $durationDays = max(1, $start->diffInDays($end) + 1);
                                        $barWidthPercent = min(100 - $leftPercent, $durationDays / $totalDays * 100);
                                        $style = "left: {$leftPercent}%; width: " . max(1.5, $barWidthPercent) . "%;";
                                    }
                                } else {
                                    $style = "display: none;";
                                }
                            @endphp
                            <div class="gantt-row" style="height: 48px; border-bottom: 1px solid var(--border-light); position: relative; display: flex; align-items: center;">
                                @if($start && $end)
                                    <div class="gantt-bar-item" style="position: absolute; {{ $style }} height: 22px; background: {{ !empty($item->critical) ? '#EDC9C6' : '#BFD3CC' }}; border-radius: 4px; overflow: hidden; display: flex; cursor: pointer; transition: transform 0.1s ease;" data-item-id="{{ $item->id }}" title="{{ $item->name }} · {{ $item->progress }}%">
                                        <div style="background: {{ !empty($item->critical)?'#A94C45':'var(--primary)' }}; height: 100%; width: {{ $item->progress }}%;"></div>
                                    </div>
                                @endif
                            </div>
                        @empty
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    <p class="help-note mt-16">Drag horizontally or use the <strong>Day</strong>, <strong>Week</strong>, and <strong>Month</strong> filters to inspect schedules. Click any project name to view details.</p>
</div>

<!-- Floating Timeline Tooltip -->
<div id="gantt-tooltip" style="position: fixed; display: none; z-index: 9999; background: #0F172A; color: #FFFFFF; padding: 8px 12px; border-radius: 6px; font-size: 12px; line-height: 1.4; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 280px;"></div>

<!-- Client-side Scale Switcher & Interactive Tooltip Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rawItems = @json($itemsJson);
    const scaleGroup = document.getElementById('portfolio-gantt-scale');
    const headerRow = document.getElementById('gantt-header-row');
    const barsContainer = document.getElementById('gantt-bars-container');
    const canvas = document.getElementById('gantt-timeline-canvas');
    const tooltip = document.getElementById('gantt-tooltip');
    if (!scaleGroup || !headerRow || !barsContainer || !canvas) return;

    let currentScale = '{{ $scale }}';

    // Tooltip handling
    barsContainer.addEventListener('mouseover', event => {
        const bar = event.target.closest('.gantt-bar-item');
        if (!bar) return;
        const id = bar.dataset.itemId;
        const item = rawItems.find(i => String(i.id) === String(id));
        if (!item) return;

        tooltip.innerHTML = `
            <strong style="display:block; font-size: 12.5px; margin-bottom: 4px; color: #F8FAFC;">${item.name}</strong>
            <div style="color: #94A3B8;">Schedule: <span style="color:#FFF;">${item.start_formatted} – ${item.end_formatted}</span></div>
            <div style="color: #94A3B8;">Progress: <span style="color:#34D399; font-weight: 600;">${item.progress}%</span> · Status: <span style="color:#FFF; text-transform: capitalize;">${item.status}</span></div>
        `;
        tooltip.style.display = 'block';
    });

    barsContainer.addEventListener('mousemove', event => {
        if (tooltip.style.display === 'block') {
            const x = Math.min(window.innerWidth - 300, event.clientX + 14);
            const y = event.clientY > 100 ? event.clientY - 70 : event.clientY + 18;
            tooltip.style.left = x + 'px';
            tooltip.style.top = y + 'px';
        }
    });

    barsContainer.addEventListener('mouseout', () => {
        tooltip.style.display = 'none';
    });

    // Segmented Scale Button Clicks
    scaleGroup.addEventListener('click', event => {
        const button = event.target.closest('.view-scale-btn[data-scale]');
        if (!button) return;
        const newScale = button.dataset.scale;
        if (newScale === currentScale) return;

        currentScale = newScale;
        scaleGroup.querySelectorAll('.view-scale-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.scale === currentScale);
        });

        // Update URL query string seamlessly
        const url = new URL(window.location.href);
        url.searchParams.set('scale', currentScale);
        window.history.replaceState({}, '', url.href);

        renderGanttScale(currentScale);
    });

    function renderGanttScale(scale) {
        tooltip.style.display = 'none';

        const valid = rawItems.filter(i => i.start && i.end);
        if (!valid.length) return;

        const DAY_MS = 86400000;
        const toUTC = str => {
            const [y, m, d] = str.split('-').map(Number);
            return Date.UTC(y, m - 1, d);
        };

        const minTime = Math.min(...valid.map(i => toUTC(i.start)));
        const maxTime = Math.max(...valid.map(i => toUTC(i.end)));

        headerRow.replaceChildren();

        if (scale === 'day') {
            const startDay = new Date(minTime);
            const endDay = new Date(maxTime);
            const totalDays = Math.max(14, Math.min(180, Math.round((endDay - startDay) / DAY_MS) + 1));
            const colWidth = 46;
            const totalWidth = Math.max(720, totalDays * colWidth);
            canvas.style.width = totalWidth + 'px';

            const todayUTC = Date.UTC(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());

            for (let d = 0; d < totalDays; d++) {
                const cur = new Date(minTime + d * DAY_MS);
                const isWeekend = cur.getUTCDay() === 0 || cur.getUTCDay() === 6;
                const isToday = cur.getTime() === todayUTC;
                const slot = document.createElement('div');
                slot.style.cssText = `flex: 0 0 ${colWidth}px; width: ${colWidth}px; border-right: 1px solid var(--border); padding: 6px 2px; font-size: 11px; text-align: center; background: ${isToday ? '#FEF3C7' : (isWeekend ? '#F8FAFC' : 'transparent')};`;
                slot.title = cur.toLocaleDateString('en-GB', { timeZone: 'UTC', weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
                slot.innerHTML = `
                    <div style="font-weight: 700; color: ${isToday ? '#92400E' : 'var(--text)'};">${cur.getUTCDate()}</div>
                    <div style="font-size: 10px; color: var(--muted);">${cur.toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'short' })}</div>
                `;
                headerRow.appendChild(slot);
            }

            // Reposition bars
            const rows = barsContainer.querySelectorAll('.gantt-row');
            rawItems.forEach((item, idx) => {
                const row = rows[idx];
                if (!row) return;
                const bar = row.querySelector('.gantt-bar-item');
                if (!item.start || !item.end) {
                    if (bar) bar.style.display = 'none';
                    return;
                }
                const itemStart = toUTC(item.start);
                const itemEnd = toUTC(item.end);
                const offsetDays = Math.max(0, Math.round((itemStart - minTime) / DAY_MS));
                const durationDays = Math.max(1, Math.round((itemEnd - itemStart) / DAY_MS) + 1);
                const leftPx = offsetDays * colWidth;
                const widthPx = Math.max(16, durationDays * colWidth);

                if (bar) {
                    bar.style.display = 'flex';
                    bar.style.left = leftPx + 'px';
                    bar.style.width = widthPx + 'px';
                }
            });

        } else if (scale === 'month') {
            const dMin = new Date(minTime);
            const dMax = new Date(maxTime);
            const startMonth = new Date(Date.UTC(dMin.getUTCFullYear(), dMin.getUTCMonth(), 1));
            const endMonth = new Date(Date.UTC(dMax.getUTCFullYear(), dMax.getUTCMonth() + 1, 0));
            const totalMonths = Math.max(3, (dMax.getUTCFullYear() - dMin.getUTCFullYear()) * 12 + dMax.getUTCMonth() - dMin.getUTCMonth() + 1);
            const colWidth = 150;
            const totalWidth = Math.max(720, totalMonths * colWidth);
            canvas.style.width = totalWidth + 'px';

            const totalSpan = endMonth.getTime() - startMonth.getTime();

            for (let m = 0; m < totalMonths; m++) {
                const cur = new Date(Date.UTC(startMonth.getUTCFullYear(), startMonth.getUTCMonth() + m, 1));
                const slot = document.createElement('div');
                slot.style.cssText = `flex: 0 0 ${colWidth}px; width: ${colWidth}px; border-right: 1px solid var(--border); padding: 12px 8px; font-size: 12px; text-align: center; font-weight: 700; color: var(--text);`;
                slot.textContent = cur.toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'short', year: 'numeric' });
                headerRow.appendChild(slot);
            }

            // Reposition bars
            const rows = barsContainer.querySelectorAll('.gantt-row');
            rawItems.forEach((item, idx) => {
                const row = rows[idx];
                if (!row) return;
                const bar = row.querySelector('.gantt-bar-item');
                if (!item.start || !item.end) {
                    if (bar) bar.style.display = 'none';
                    return;
                }
                const itemStart = toUTC(item.start);
                const itemEnd = toUTC(item.end);
                const leftPercent = Math.max(0, (itemStart - startMonth.getTime()) / totalSpan * 100);
                const duration = Math.max(DAY_MS, itemEnd - itemStart + DAY_MS);
                const widthPercent = Math.min(100 - leftPercent, duration / totalSpan * 100);

                if (bar) {
                    bar.style.display = 'flex';
                    bar.style.left = leftPercent + '%';
                    bar.style.width = Math.max(1.5, widthPercent) + '%';
                }
            });

        } else { // week (default)
            const dMin = new Date(minTime);
            dMin.setUTCDate(dMin.getUTCDate() - (dMin.getUTCDay() + 6) % 7); // Start of week (Monday)
            const dMax = new Date(maxTime);
            dMax.setUTCDate(dMax.getUTCDate() + (7 - dMax.getUTCDay()) % 7); // End of week (Sunday)
            const totalDays = Math.max(14, Math.round((dMax - dMin) / DAY_MS) + 1);
            const weeks = Math.ceil(totalDays / 7);
            const colWidth = 110;
            const totalWidth = Math.max(720, weeks * colWidth);
            canvas.style.width = totalWidth + 'px';

            for (let w = 0; w < weeks; w++) {
                const cur = new Date(dMin.getTime() + w * 7 * DAY_MS);
                const slot = document.createElement('div');
                slot.style.cssText = `flex: 0 0 ${colWidth}px; width: ${colWidth}px; border-right: 1px solid var(--border); padding: 8px; font-size: 11px; color: var(--muted); text-align: center;`;
                slot.innerHTML = `
                    <div style="font-weight: 650; color: var(--text);">W${w + 1}</div>
                    <div>${cur.toLocaleDateString('en-GB', { timeZone: 'UTC', day: '2-digit', month: 'short' })}</div>
                `;
                headerRow.appendChild(slot);
            }

            // Reposition bars
            const rows = barsContainer.querySelectorAll('.gantt-row');
            rawItems.forEach((item, idx) => {
                const row = rows[idx];
                if (!row) return;
                const bar = row.querySelector('.gantt-bar-item');
                if (!item.start || !item.end) {
                    if (bar) bar.style.display = 'none';
                    return;
                }
                const itemStart = toUTC(item.start);
                const itemEnd = toUTC(item.end);
                const leftPercent = Math.max(0, (itemStart - dMin.getTime()) / (totalDays * DAY_MS) * 100);
                const durationDays = Math.max(1, Math.round((itemEnd - itemStart) / DAY_MS) + 1);
                const widthPercent = Math.min(100 - leftPercent, durationDays / totalDays * 100);

                if (bar) {
                    bar.style.display = 'flex';
                    bar.style.left = leftPercent + '%';
                    bar.style.width = Math.max(1.5, widthPercent) + '%';
                }
            });
        }
    }
});
</script>
@endsection
