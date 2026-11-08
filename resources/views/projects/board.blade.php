@extends('layouts.app')
@section('title', 'Tasks Board · ' . $project->name)
@section('content')
@include('projects._tabs')
<!-- Screen 6: Tasks (Kanban View) -->
<div class="page-head">
    <div>
        <div class="breadcrumb-trail">
            <a href="{{ route('projects.index') }}">Projects</a>
            <span class="crumb-sep">›</span>
            <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
            <span class="crumb-sep">›</span>
            <span>Tasks</span>
        </div>
        <div class="title-row">
            <h1>{{ $project->name }}</h1>
            <span class="pill {{ $project->status }}">{{ ucfirst($project->status) }}</span>
        </div>
        <p class="muted">Organize, review, and advance tasks across workflow stages.</p>
    </div>

    <!-- View Switcher (Screen 6: List | Kanban) -->
    <div class="inline-actions">
        <div class="view-switcher">
            <a href="{{ route('projects.show', $project) }}#tasks" class="view-btn">List</a>
            <span class="view-btn active">Kanban</span>
        </div>
        @if($canManage && $project->status !== 'archived')
            <button type="button" class="btn btn-primary btn-sm" data-show-dialog="add-task-dialog">+ New Task</button>
        @endif
    </div>
</div>



<div class="board-columns">
    @php
        $lanes = [
            'todo' => ['label' => 'To Do', 'class' => 'todo'],
            'in_progress' => ['label' => 'In Progress', 'class' => 'in_progress'],
            'in_review' => ['label' => 'In Review', 'class' => 'in_review'],
            'completed' => ['label' => 'Completed', 'class' => 'completed'],
        ];
    @endphp

    @foreach($lanes as $statusKey => $lane)
        @php
            $laneTasks = $tasks->filter(function($t) use ($statusKey) {
                if ($statusKey === 'in_review') {
                    return $t->status === 'in_review' || $t->status === 'on_hold';
                }
                return $t->status === $statusKey;
            });
        @endphp
        <section class="board-lane">
            <div class="board-head">
                <span class="board-lane-header-badge {{ $lane['class'] }}">
                    {{ $lane['label'] }}
                    <span class="board-count">({{ $laneTasks->count() }})</span>
                </span>
            </div>

            <div class="board-stack">
                @forelse($laneTasks as $task)
                    <article class="board-task">
                        <div class="board-task-top">
                            <span class="task-code">{{ $numbers[$task->id] ?? $task->code }}</span>
                            <span class="pill {{ $task->priority === 'high' ? 'on_hold' : 'todo' }}" style="font-size: 10px; padding: 2px 6px;">
                                {{ ucfirst($task->priority) }}
                            </span>
                        </div>

                        <a class="board-task-title" href="{{ route('projects.show', $project) }}">
                            {{ $task->title }}
                        </a>

                        <div class="board-task-meta">
                            <span>Due: {{ $task->ends_at->format('d M') }}</span>
                            <div class="board-task-assignee">
                                @if($task->assignees->isNotEmpty())
                                    <span class="avatar avatar-sm" title="{{ $task->assignees->pluck('name')->implode(', ') }}">
                                        {{ strtoupper(substr($task->assignees->first()->name, 0, 1)) }}
                                    </span>
                                @else
                                    <span style="font-size: 11px; color: #9CA3AF;">—</span>
                                @endif
                            </div>
                        </div>

                        @if($canManage)
                            <form method="post" action="{{ route('tasks.update', $task) }}" class="mt-8">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="progress" value="{{ $task->progress }}">
                                <select name="status" data-auto-submit style="height: 28px; font-size: 11.5px; padding: 2px 6px; background: var(--surface);">
                                    <option value="" disabled selected>Move stage...</option>
                                    <option value="todo">To Do</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold">In Review</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="empty" style="padding: 24px 8px; font-size: 12px;">
                        No tasks in this lane
                    </div>
                @endforelse
            </div>
        </section>
    @endforeach
</div>

@if($canManage && $project->status !== 'archived')
    <dialog id="add-task-dialog" class="modal">
        <div class="modal-head">
            <h2>New Task</h2>
            <button type="button" class="icon-btn" data-close-dialog>×</button>
        </div>
        <form method="post" action="{{ route('tasks.store', $project) }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label>Task name <span class="required">*</span>
                <input name="title" required maxlength="200" placeholder="e.g. Develop Header">
            </label>
            <div class="two-fields">
                <label>Start Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="starts_at" required>
                </label>
                <label>End Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="ends_at" required>
                </label>
            </div>
            <div class="two-fields">
                <label>Assign to
                    <select name="assigned_user_id">
                        <option value="">Assign later</option>
                        @foreach($project->memberships as $member)
                            <option value="{{ $member->user_id }}">{{ $member->user->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Priority
                    <select name="priority">
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                    </select>
                </label>
            </div>
            <label>Description
                <textarea name="description" rows="3" placeholder="Brief details about this task"></textarea>
            </label>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit">Create Task</button>
            </div>
        </form>
    </dialog>
@endif
@endsection
