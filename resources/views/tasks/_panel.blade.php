<div class="panel-task-top">
    <span class="muted">Task {{ $task->code }} @if($task->parent) · Subtask @else · Main task @endif</span>
    <h3>{{ $task->title }}</h3>
    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
        <span class="pill {{ $task->status }}">{{ ['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'][$task->status] ?? ucfirst($task->status) }}</span>
        @if($task->isOverdue())
            <span class="pill" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700;">Overdue</span>
        @endif
    </div>
</div>

<div class="panel-details-grid">
    <div><small>Assigned to</small><b>{{ $task->assignees->pluck('name')->join(', ') ?: 'Not assigned' }}</b></div>
    <div><small>Priority</small><b>{{ ucfirst($task->priority) }}</b></div>
    <div><small>Start date</small><b>{{ $task->starts_at->format('d M Y') }}</b></div>
    <div><small>Start time</small><b>{{ $task->starts_at->format('h:i A') }}</b></div>
    <div><small>End date</small><b style="{{ $task->isOverdue() ? 'color: #DC2626; font-weight: 700;' : '' }}">{{ $task->ends_at->format('d M Y') }} @if($task->isOverdue()) <span style="color: #DC2626; font-size: 11px; font-weight: 700;">(Overdue)</span> @endif</b></div>
    <div><small>End time</small><b style="{{ $task->isOverdue() ? 'color: #DC2626; font-weight: 700;' : '' }}">{{ $task->ends_at->format('h:i A') }}</b></div>
</div>

@if($canUpdate && !$task->children()->exists())
    <form method="post" action="{{ route('tasks.update', $task) }}" class="panel-section panel-update" data-panel-section="update">
        @csrf
        @method('PATCH')
        <b>Quick update</b>
        <div class="two-fields">
            <label>Status
                <select name="status" required>
                    @foreach(['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'] as $val=>$label)
                        <option value="{{ $val }}" @selected($task->status===$val)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Progress (%)
                <input type="number" name="progress" min="0" max="100" value="{{ $task->progress }}" required>
            </label>
        </div>
        <button class="btn btn-primary btn-sm">Save update</button>
    </form>
@else
    <div class="panel-section">
        <b>Progress</b>
        <div class="progress-line"><div style="width:{{ $task->progress }}%"></div></div>
        <span class="muted">{{ $task->progress }}% completed</span>
    </div>
@endif


@if($canManage)
<details class="panel-inner-details"><summary>Edit task details and schedule</summary>
    <form method="post" action="{{ route('tasks.details', $task) }}" class="stack">
        @csrf
        @method('PUT')
        <label>Task name
            <input name="title" value="{{ $task->title }}" maxlength="200" required>
        </label>
        <div class="two-fields">
            <label>Start date & time
                <input type="datetime-local" name="starts_at" value="{{ $task->starts_at->format('Y-m-d\TH:i') }}" required>
            </label>
            <label>End date & time
                <input type="datetime-local" name="ends_at" value="{{ $task->ends_at->format('Y-m-d\TH:i') }}" required>
            </label>
        </div>
        <div class="two-fields">
            <label>Priority
                <select name="priority">
                    <option value="low" @selected($task->priority==='low')>Low</option>
                    <option value="medium" @selected($task->priority==='medium')>Medium</option>
                    <option value="high" @selected($task->priority==='high')>High</option>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="todo" @selected($task->status==='todo')>To Do</option>
                    <option value="in_progress" @selected($task->status==='in_progress')>In Progress</option>
                    <option value="on_hold" @selected($task->status==='on_hold')>On Hold</option>
                    <option value="completed" @selected($task->status==='completed')>Completed</option>
                </select>
            </label>
        </div>
        <div class="two-fields">
            <label>Progress (%)
                <input type="number" name="progress" min="0" max="100" value="{{ $task->progress }}">
            </label>
            <label>Assigned member
                <select name="assigned_user_id">
                    <option value="">Unassigned</option>
                    @foreach($task->project->memberships->unique('user_id') as $member)
                        <option value="{{ $member->user_id }}" @selected($task->assignees->pluck('id')->contains($member->user_id))>{{ $member->user?->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label>Description
            <textarea name="description" rows="2">{{ $task->description }}</textarea>
        </label>
        <button class="btn btn-primary btn-sm">Save task details</button>
    </form>
</details>

<details class="panel-inner-details" data-panel-section="assignment"><summary>Change assignment</summary>
    <form method="post" action="{{ route('tasks.assign', $task) }}" class="stack">
        @csrf
        <label>Assign another person
            <select name="user_id" required>
                <option value="">Select project member</option>
                @foreach($task->project->memberships->unique('user_id') as $member)
                    <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn btn-outline btn-sm">Assign person</button>
    </form>
    @foreach($task->assignees as $assignee)
        <form method="post" action="{{ route('tasks.unassign', $task) }}" class="panel-unassign">
            @csrf
            <input type="hidden" name="user_id" value="{{ $assignee->id }}">
            <span>{{ $assignee->name }}</span>
            <button class="text-action" type="submit" style="color: #D32F2F;">Remove</button>
        </form>
    @endforeach
</details>
@endif

@if($task->baseline_starts_at && $task->baseline_ends_at)
    <p class="panel-muted-note">Original planned finish: {{ $task->baseline_ends_at->format('d M Y, h:i A') }}</p>
@endif

@if($task->description)
    <div class="panel-section">
        <b>Description</b>
        <p class="muted">{{ $task->description }}</p>
    </div>
@endif


<!-- Predecessor Dependencies Management -->
<div class="panel-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <b>Predecessor Dependencies</b>
        <span class="muted" style="font-size: 11px;">{{ $task->dependencies->count() }} linked</span>
    </div>

    @if($task->dependencies->isNotEmpty())
        <div class="stack" style="gap: 6px; margin-bottom: 10px;">
            @foreach($task->dependencies as $dependency)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 7px 10px; background: #F5F6F7; border: 1px solid #E1E4E8; border-radius: 4px; font-size: 12.5px;">
                    <div style="min-width: 0; margin-right: 8px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-weight: 600; color: #22272B;">{{ $dependency->code }}</span>
                            <span class="pill {{ $dependency->status }}" style="font-size: 10px; padding: 1px 6px;">{{ ucwords(str_replace('_', ' ', $dependency->status)) }}</span>
                        </div>
                        <span style="color: #22272B; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;">{{ $dependency->title }}</span>
                        <small class="muted" style="display: block; font-size: 11px;">Finishes: {{ $dependency->ends_at->format('d M Y') }}</small>
                    </div>
                    @if($canManage)
                        <form method="post" action="{{ route('tasks.dependencies.delete', [$task, $dependency]) }}" onsubmit="return confirm('Remove dependency on {{ $dependency->code }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-action" style="color: #D32F2F; font-size: 11.5px; border: none; background: none; cursor: pointer; padding: 2px 4px;">Remove</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="muted" style="font-size: 12px; margin-bottom: 8px;">No predecessor dependencies. This task can start independently.</p>
    @endif

    @if($canManage)
        @if(isset($candidateTasks) && $candidateTasks->isNotEmpty())
            <form method="post" action="{{ route('tasks.dependencies.store', $task) }}" style="display: flex; gap: 6px; align-items: center; margin-top: 8px;">
                @csrf
                <select name="depends_on_task_id" required style="flex: 1; font-size: 12px; padding: 6px 8px; border: 1px solid #D0D7DE; border-radius: 4px; background: #FFFFFF;">
                    <option value="">Select predecessor task...</option>
                    @foreach($candidateTasks as $cand)
                        <option value="{{ $cand->id }}">{{ $cand->code }} – {{ \Illuminate\Support\Str::limit($cand->title, 28) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline btn-sm" style="white-space: nowrap; font-size: 11.5px; padding: 5px 10px;">+ Add</button>
            </form>
        @else
            <p class="muted" style="font-size: 11.5px; margin: 0;">No other tasks available to link as predecessors.</p>
        @endif
    @endif
</div>

<div class="panel-section" id="delay-section">
    <div class="panel-section-title">
        <b>Delay & blockers</b>
        @if($task->openDelay)<span class="pill on_hold">Open issue</span>@endif
    </div>
    @if($task->openDelay)
        <div class="blocker-detail">
            <b>{{ $delayReasons[$task->openDelay->reason_code] ?? $task->openDelay->reason_code }}</b>
            @if($task->openDelay->details)<p class="muted">{{ $task->openDelay->details }}</p>@endif
            <small>Reported by: {{ $task->openDelay->reporter?->name ?? 'Team member' }}</small>
            <small>Follow-up owner: {{ $task->openDelay->actionOwner?->name ?? 'Not assigned' }}</small>
            <small>Responsible party: {{ $task->openDelay->responsible_party_name ?: ucwords($task->openDelay->responsible_party_type) }}</small>
            @if($task->openDelay->revised_expected_end_at)<small>Revised target: {{ $task->openDelay->revised_expected_end_at->format('d M Y, h:i A') }}</small>@endif
        </div>
        @if($canManage)
            <form method="post" action="{{ route('tasks.delays.resolve', $task->openDelay) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-outline btn-sm">Mark resolved</button>
            </form>
        @endif
    @else
        <p class="muted">No blocker has been reported. An overdue date alone does not establish the reason.</p>
    @endif
    @if($canUpdate)
        <details class="panel-inner-details">
            <summary>{{ $task->openDelay ? 'Update delay report' : '+ Report a delay' }}</summary>
            <form method="post" action="{{ route('tasks.delays.store', $task) }}" class="stack">
                @csrf
                <label>What is causing the delay?
                    <select name="reason_code" required>
                        <option value="">Choose a reason</option>
                        @foreach($delayReasons as $code=>$label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Short explanation (optional)
                    <textarea name="details" rows="2" maxlength="2000" placeholder="What is blocked and what happened?"></textarea>
                </label>
                <label>Involved party
                    <select name="responsible_party_type" required>
                        <option value="unknown">Not confirmed</option>
                        <option value="internal">Internal team</option>
                        <option value="supplier">Supplier/vendor</option>
                        <option value="department">Another department</option>
                        <option value="external">External party</option>
                    </select>
                </label>
                <label>Party name (optional)
                    <input name="responsible_party_name" maxlength="160" placeholder="e.g. Supplier name or department">
                </label>
                <label>Who will follow up?
                    <select name="action_owner_id">
                        <option value="">Assign later</option>
                        @foreach($task->project->memberships->unique('user_id') as $member)
                            <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Revised expected finish (optional)
                    <input type="datetime-local" name="revised_expected_end_at">
                </label>
                <button class="btn btn-primary btn-sm">Save delay report</button>
            </form>
        </details>
    @endif
</div>

<div class="panel-section">
    <b>Files</b>
    @forelse($task->attachments as $attachment)
        <a class="panel-file" href="{{ route('attachments.download', $attachment) }}">
            <span>{{ $attachment->original_name }}</span>
            <span class="muted" style="font-size: 11px;">Download</span>
        </a>
    @empty
        <p class="muted">No files attached.</p>
    @endforelse
    @if($canUpload)
        <form method="post" action="{{ route('tasks.upload', $task) }}" enctype="multipart/form-data" class="panel-upload">
            @csrf
            <label>Add files
                <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png" required>
            </label>
            <button class="btn btn-outline btn-sm">Upload</button>
        </form>
    @endif
</div>

<div class="panel-section">
    <b>Comments & updates</b>
    @forelse($task->comments->sortByDesc('created_at')->take(5) as $comment)
        <div class="panel-comment">
            <b>{{ $comment->user?->name }}</b>
            <small>{{ $comment->created_at->format('d M, H:i') }}</small>
            <p>{{ $comment->body }}</p>
        </div>
    @empty
        <p class="muted">No comments yet.</p>
    @endforelse
    @if($canUpdate)
        <form method="post" action="{{ route('tasks.comments.store', $task) }}" class="stack">
            @csrf
            <label>Add a comment
                <textarea name="body" rows="2" maxlength="2000" required placeholder="Write a short update..."></textarea>
            </label>
            <button class="btn btn-outline btn-sm">Post comment</button>
        </form>
    @endif
</div>

<div class="panel-section">
    <a class="text-action" href="{{ route('tasks.show', $task) }}">Open full task page →</a>
</div>
