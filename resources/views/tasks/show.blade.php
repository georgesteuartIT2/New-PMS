@extends('layouts.app')
@section('title', $task->title)
@section('content')

<div class="page-head">
    <div>
        <div class="breadcrumb-trail">
            <a href="{{ route('projects.index') }}">Projects</a>
            <span class="crumb-sep">›</span>
            <a href="{{ route('projects.show', $task->project) }}">{{ $task->project->name }}</a>
            <span class="crumb-sep">›</span>
            <a href="{{ route('projects.show', $task->project) }}">Tasks</a>
            <span class="crumb-sep">›</span>
            <span>{{ $task->title }}</span>
        </div>
        <div class="title-row" style="margin-top: 6px;">
            <h1>{{ $task->title }}</h1>
            <div style="display: flex; gap: 6px; align-items: center;">
                <span class="pill {{ $task->status }}">{{ ucwords(str_replace('_', ' ', $task->status)) }}</span>
                @if($task->isOverdue())
                    <span class="pill" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700;">Overdue</span>
                @endif
            </div>
        </div>
        <p class="muted">{{ $task->code }} · {{ $task->project->name }}</p>
    </div>

    @if($canManage)
        <div class="inline-actions">
            <button class="btn btn-outline btn-sm" type="button" data-show-dialog="task-edit-dialog">Edit Task Details</button>
            <form method="post" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger-outline btn-sm" type="submit">Delete Task</button>
            </form>
        </div>
    @endif
</div>

<!-- Row 1: Task Information & Quick Update -->
<div class="two-fields" style="align-items: flex-start; margin-bottom: 24px;">
    <!-- Main Task Information Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-head">
            <h2>Task Information</h2>
            <span class="pill {{ $task->priority === 'high' ? 'on_hold' : 'todo' }}">Priority: {{ ucfirst($task->priority) }}</span>
        </div>
        <div class="detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <small class="muted">Status</small>
                <div style="margin-top: 4px;">
                    <span class="pill {{ $task->status }}">{{ ucwords(str_replace('_', ' ', $task->status)) }}</span>
                </div>
            </div>
            <div>
                <small class="muted">Priority</small>
                <strong style="display: block; margin-top: 4px; font-weight: 600;">{{ ucfirst($task->priority) }}</strong>
            </div>
            <div>
                <small class="muted">Start Date</small>
                <strong style="display: block; margin-top: 4px;">{{ $task->starts_at->format('d M Y, h:i A') }}</strong>
            </div>
            <div>
                <small class="muted">Due Date</small>
                <strong style="display: block; margin-top: 4px; {{ $task->isOverdue() ? 'color: #DC2626;' : '' }}">
                    {{ $task->ends_at->format('d M Y, h:i A') }}
                    @if($task->isOverdue())
                        <span style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; margin-left: 6px; display: inline-block;">Overdue</span>
                    @endif
                </strong>
            </div>
            <div>
                <small class="muted">Assigned Member</small>
                <strong style="display: block; margin-top: 4px;">{{ $task->assignees->pluck('name')->implode(', ') ?: 'Unassigned' }}</strong>
            </div>
            <div>
                <small class="muted">Progress</small>
                <div class="progress-cell" style="margin-top: 6px;">
                    <div class="progress-line"><div style="width: {{ $task->progress }}%"></div></div>
                    <small style="font-weight: 600;">{{ $task->progress }}%</small>
                </div>
            </div>
        </div>

        @if($task->baseline_starts_at && $task->baseline_ends_at)
            <div class="mt-16" style="padding-top: 10px; border-top: 1px solid var(--border-light, #E1E4E8);">
                <small class="muted">Baseline Planned Finish: {{ $task->baseline_ends_at->format('d M Y, h:i A') }}</small>
            </div>
        @endif

        <div class="mt-16" style="padding-top: 14px; border-top: 1px solid var(--border-light, #E1E4E8);">
            <small class="muted block" style="font-weight: 600;">Description</small>
            <p style="margin-top: 6px; color: #4B5563; font-size: 13.5px; line-height: 1.5;">{{ $task->description ?: 'No description provided.' }}</p>
        </div>
    </div>

    <!-- Quick Status & Progress Updater -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-head">
            <h2>Quick Update</h2>
        </div>
        @if($canUpdate)
            <form method="post" action="{{ route('tasks.update', $task) }}" class="stack">
                @csrf
                @method('PATCH')
                <label>Status
                    <select name="status" required>
                        @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $val => $lbl)
                            <option value="{{ $val }}" @selected($task->status === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Progress Completion: <span id="progress-val-badge" style="font-weight: 600;">{{ $task->progress }}%</span>
                    <input type="range" min="0" max="100" step="5" value="{{ $task->progress }}" name="progress" id="quick-progress-range" style="width: 100%; margin-top: 8px;" oninput="document.getElementById('progress-val-badge').textContent = this.value + '%'">
                </label>
                <button class="btn btn-primary btn-sm align-self-start mt-8" type="submit">Save Update</button>
            </form>
        @else
            <p class="muted text-sm">You have view-only access to this task.</p>
        @endif
    </div>
</div>

<!-- Row 2: Predecessor Dependencies & Delays -->
<div class="two-fields" style="align-items: flex-start; margin-bottom: 24px;">
    <!-- Predecessor Dependencies Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-head">
            <h2>Predecessor Dependencies</h2>
            <span class="muted text-sm">{{ $task->dependencies->count() }} linked</span>
        </div>
        <p class="muted text-sm" style="margin-bottom: 14px;">
            Tasks that must be completed before this task can start.
        </p>

        @if($task->dependencies->isNotEmpty())
            <div class="stack" style="gap: 8px;">
                @foreach($task->dependencies as $dependency)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #F5F6F7; border: 1px solid var(--border-light, #E1E4E8); border-radius: 6px;">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="pill {{ $dependency->status }}" style="font-size: 11px;">{{ $dependency->code }}</span>
                            <div style="min-width: 0;">
                                <a href="{{ route('tasks.show', $dependency) }}" class="strong-link" style="display: block; font-size: 13px; font-weight: 500; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">{{ $dependency->title }}</a>
                                <span class="muted text-sm" style="font-size: 11.5px;">Planned finish: {{ $dependency->ends_at->format('d M Y') }} · Status: {{ ucwords(str_replace('_', ' ', $dependency->status)) }}</span>
                            </div>
                        </div>
                        @if($canManage)
                            <form action="{{ route('tasks.dependencies.delete', [$task, $dependency]) }}" method="post" onsubmit="return confirm('Remove dependency link to {{ $dependency->code }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #EF9A9A; padding: 3px 8px; font-size: 11.5px;">Remove</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div style="padding: 16px; background: #F9FAFB; border: 1px dashed #D0D7DE; border-radius: 6px; text-align: center;">
                <p class="muted text-sm" style="margin: 0;">No predecessor dependencies linked. This task can start independently.</p>
            </div>
        @endif

        @if($canManage)
            <div class="mt-16" style="padding-top: 14px; border-top: 1px solid var(--border-light, #E1E4E8);">
                <span class="muted text-sm" style="font-weight: 600; display: block; margin-bottom: 8px;">Add Predecessor Dependency</span>
                @if(isset($candidateTasks) && $candidateTasks->isNotEmpty())
                    <form method="post" action="{{ route('tasks.dependencies.store', $task) }}" style="display: flex; gap: 8px; align-items: center;">
                        @csrf
                        <select name="depends_on_task_id" required style="flex: 1; padding: 7px 10px; border: 1px solid #D0D7DE; border-radius: 4px; font-size: 12.5px; background: #FFFFFF;">
                            <option value="">Select predecessor task...</option>
                            @foreach($candidateTasks as $cand)
                                <option value="{{ $cand->id }}">{{ $cand->code }} – {{ $cand->title }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit" style="white-space: nowrap;">+ Add Dependency</button>
                    </form>
                @else
                    <p class="muted text-sm" style="margin: 0;">No other candidate tasks available to link.</p>
                @endif
            </div>
        @endif
    </div>

    <!-- Delay & Blockers Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-head">
            <h2>Delay & Blockers</h2>
            @if($task->openDelay)<span class="pill on_hold">Open Blocker</span>@endif
        </div>

        @if($task->openDelay)
            <div class="blocker-detail" style="padding: 12px; background: #FFF5F5; border: 1px solid #FFCDD2; border-radius: 6px; margin-bottom: 14px;">
                <strong style="color: #C62828; display: block; font-size: 13.5px;">{{ $delayReasons[$task->openDelay->reason_code] ?? $task->openDelay->reason_code }}</strong>
                @if($task->openDelay->details)
                    <p class="muted" style="margin: 6px 0; font-size: 12.5px; color: #4B5563;">{{ $task->openDelay->details }}</p>
                @endif
                <div style="font-size: 12px; color: #6B7280; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 8px;">
                    <div>Reported by: <strong>{{ $task->openDelay->reporter?->name ?? 'Team Member' }}</strong></div>
                    <div>Follow-up owner: <strong>{{ $task->openDelay->actionOwner?->name ?? 'Unassigned' }}</strong></div>
                    <div>Party: <strong>{{ $task->openDelay->responsible_party_name ?: ucwords($task->openDelay->responsible_party_type) }}</strong></div>
                    @if($task->openDelay->revised_expected_end_at)
                        <div>Revised finish: <strong>{{ $task->openDelay->revised_expected_end_at->format('d M Y') }}</strong></div>
                    @endif
                </div>
            </div>
            @if($canManage)
                <form method="post" action="{{ route('tasks.delays.resolve', $task->openDelay) }}" style="margin-bottom: 12px;">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-outline btn-sm">Mark Blocker Resolved</button>
                </form>
            @endif
        @else
            <p class="muted text-sm" style="margin-bottom: 14px;">No blocker has been reported on this task.</p>
        @endif

        @if($canUpdate)
            <details class="panel-inner-details" style="border: 1px solid var(--border-light, #E1E4E8); border-radius: 6px; padding: 10px 14px; background: #FAFAFA;">
                <summary style="cursor: pointer; font-weight: 500; font-size: 13px; color: var(--text, #22272B);">{{ $task->openDelay ? 'Update Blocker Report' : '+ Report a Delay' }}</summary>
                <form method="post" action="{{ route('tasks.delays.store', $task) }}" class="stack mt-16">
                    @csrf
                    <label>Reason for delay <span class="required">*</span>
                        <select name="reason_code" required>
                            <option value="">Choose a reason...</option>
                            @foreach($delayReasons ?? [] as $code=>$label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Explanation
                        <textarea name="details" rows="2" maxlength="2000" placeholder="Describe the blocker and next steps..."></textarea>
                    </label>
                    <div class="two-fields">
                        <label>Responsible party
                            <select name="responsible_party_type" required>
                                <option value="unknown">Not confirmed</option>
                                <option value="internal">Internal team</option>
                                <option value="supplier">Supplier / Vendor</option>
                                <option value="department">Another department</option>
                                <option value="external">External party</option>
                            </select>
                        </label>
                        <label>Party name
                            <input name="responsible_party_name" maxlength="160" placeholder="e.g. Vendor name">
                        </label>
                    </div>
                    <div class="two-fields">
                        <label>Follow-up owner
                            <select name="action_owner_id">
                                <option value="">Assign later</option>
                                @foreach($task->project->memberships->unique('user_id') as $member)
                                    <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Revised expected finish
                            <input type="datetime-local" name="revised_expected_end_at">
                        </label>
                    </div>
                    <button class="btn btn-primary btn-sm align-self-start" type="submit">Submit Report</button>
                </form>
            </details>
        @endif
    </div>
</div>

<!-- Row 3: Attachments & Comments -->
<div class="two-fields" style="align-items: flex-start;">
    <!-- Attachments Card -->
    <div class="card">
        <div class="card-head">
            <h2>Attachments</h2>
            <span class="muted text-sm">{{ $task->attachments->count() }} files</span>
        </div>
        @forelse($task->attachments as $file)
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-light, #E1E4E8);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="{{ route('attachments.download', $file) }}" class="strong-link">{{ $file->original_name }}</a>
                    <small class="muted">({{ number_format($file->size_bytes / 1024) }} KB)</small>
                </div>
                @if($canUpload)
                    <form action="{{ route('attachments.delete', $file) }}" method="post" onsubmit="return confirm('Remove file?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #EF9A9A; padding: 2px 6px; font-size: 11px;">Remove</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="muted text-sm">No attachments uploaded yet.</p>
        @endforelse

        @if($canUpload)
            <form method="post" action="{{ route('tasks.upload', $task) }}" enctype="multipart/form-data" class="stack mt-16">
                @csrf
                <label class="custom-checkbox-row" style="background: var(--surface, #F5F6F7); border: 1px dashed var(--border, #D0D7DE); border-radius: 4px; padding: 8px 12px; cursor: pointer;">
                    <input name="files[]" type="file" multiple required style="display: none;" onchange="this.nextElementSibling.textContent = this.files.length + ' file(s) selected'">
                    <span style="font-size: 12.5px; color: var(--text, #22272B);">+ Upload file(s) (PDF, Word, Excel, Images)</span>
                </label>
                <button class="btn btn-outline btn-sm align-self-start" type="submit">Upload</button>
            </form>
        @endif
    </div>

    <!-- Comments & Activity Card -->
    <div class="card">
        <div class="card-head">
            <h2>Comments & Updates</h2>
            <span class="muted text-sm">{{ $task->comments->count() }} notes</span>
        </div>
        @forelse($task->comments->sortByDesc('created_at') as $comment)
            <div style="display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-light, #E1E4E8);">
                <span class="avatar avatar-sm" style="width: 28px; height: 28px; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; background: #E2E8F0; border-radius: 50%;">{{ strtoupper(substr($comment->user->name, 0, 1)) }}</span>
                <div class="grow" style="min-width: 0;">
                    <div style="display: flex; justify-content: space-between;">
                        <b>{{ $comment->user->name }}</b>
                        <small class="muted">{{ $comment->created_at->diffForHumans() }}</small>
                    </div>
                    <p style="font-size: 12.5px; margin-top: 4px; color: #4B5563;">{{ $comment->body }}</p>
                </div>
            </div>
        @empty
            <p class="muted text-sm">No comments yet.</p>
        @endforelse

        @if($task->project->status === 'active' && ($canManage || $canUpdate))
            <form method="post" action="{{ route('tasks.comments.store', $task) }}" class="stack mt-16">
                @csrf
                <textarea name="body" placeholder="Write a comment or status update..." rows="2" required></textarea>
                <button class="btn btn-outline btn-sm align-self-start" type="submit">Post Comment</button>
            </form>
        @endif
    </div>
</div>

<!-- Complete Task Edit Modal Dialog -->
@if($canManage)
    <dialog id="task-edit-dialog" class="modal" style="max-width: 580px;">
        <div class="modal-head">
            <h2>Edit Task Details</h2>
            <button class="icon-btn" data-close-dialog type="button" aria-label="Close dialog">×</button>
        </div>
        <form method="post" action="{{ route('tasks.details', $task) }}" class="stack mt-16">
            @csrf
            @method('PUT')
            <label>Task Name <span class="required">*</span>
                <input name="title" required value="{{ $task->title }}" maxlength="200">
            </label>
            <div class="two-fields">
                <label>Status <span class="required">*</span>
                    <select name="status" required>
                        <option value="todo" @selected($task->status === 'todo')>To Do</option>
                        <option value="in_progress" @selected($task->status === 'in_progress')>In Progress</option>
                        <option value="on_hold" @selected($task->status === 'on_hold')>On Hold</option>
                        <option value="completed" @selected($task->status === 'completed')>Completed</option>
                    </select>
                </label>
                <label>Progress (%)
                    <input type="number" name="progress" min="0" max="100" value="{{ $task->progress }}">
                </label>
            </div>
            <div class="two-fields">
                <label>Start Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="starts_at" value="{{ $task->starts_at->format('Y-m-d\TH:i') }}" required>
                </label>
                <label>Due Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="ends_at" value="{{ $task->ends_at->format('Y-m-d\TH:i') }}" required>
                </label>
            </div>
            <div class="two-fields">
                <label>Priority <span class="required">*</span>
                    <select name="priority" required>
                        <option value="low" @selected($task->priority === 'low')>Low</option>
                        <option value="medium" @selected($task->priority === 'medium')>Medium</option>
                        <option value="high" @selected($task->priority === 'high')>High</option>
                    </select>
                </label>
                <label>Assigned Member
                    <select name="assigned_user_id">
                        <option value="">Unassigned</option>
                        @foreach($task->project->memberships->unique('user_id') as $member)
                            <option value="{{ $member->user_id }}" @selected($task->assignees->pluck('id')->contains($member->user_id))>{{ $member->user?->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label>Description (Optional)
                <textarea name="description" rows="3">{{ $task->description }}</textarea>
            </label>
            <div class="form-actions mt-16">
                <button class="btn btn-ghost" data-close-dialog type="button">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </dialog>
@endif

@endsection
