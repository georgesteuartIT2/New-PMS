<div class="approval-request-content" data-approval-content>
    <header class="ar-head"><div><span class="ar-eyebrow">Project manager assignment</span><h2 id="approval-request-title" tabindex="-1">{{ $project->name }}</h2><p>{{ $project->code }} &middot; {{ $project->subsidiary?->name ?? 'General Group' }}</p></div><button type="button" class="ar-close" data-close-approval aria-label="Close review">&times;</button></header>
    <div class="ar-body">
        <section class="ar-section"><h3>Project details</h3><dl class="ar-facts">
            <div><dt>Project manager</dt><dd>{{ $project->manager?->name ?? 'Not assigned' }}</dd></div>
            <div><dt>Schedule</dt><dd>@if($project->approval_stage && !$project->plan_initialized)Set by the PM after acceptance@else{{ $project->starts_on->format('d M Y') }} &ndash; {{ $project->ends_on->format('d M Y') }}@endif</dd></div>
            <div><dt>Created by</dt><dd>{{ $project->creator?->name ?? 'PMO' }}<small>{{ $project->created_at->format('d M Y, H:i') }}</small></dd></div>
            <div><dt>Request received</dt><dd>{{ $invitation->created_at->format('d M Y, H:i') }}</dd></div>
            <div><dt>Project template</dt><dd>{{ $project->template?->name ?? 'Blank project' }}</dd></div>
            <div><dt>Working hours</dt><dd>{{ $project->template?->working_hours ?? 'As scheduled in the task plan' }}</dd></div>
        </dl><div class="ar-description"><h4>Scope / Description</h4><p>{{ $project->description ?: 'No project description provided.' }}</p></div></section>
        <section class="ar-section"><header class="ar-section-head"><h3>Task plan <span>{{ $leafCount }} executable tasks</span></h3><a href="{{ route('projects.show',$project) }}#gantt">Open full project &rarr;</a></header>
            @if($tasks->isEmpty())<p class="ar-empty">No tasks have been planned yet. The project manager can prepare the plan after acceptance.</p>
            @else<div class="ar-table-wrap"><table class="ar-task-table"><thead><tr><th>Task / Subtask</th><th>Assignee</th><th>Starts</th><th>Due</th><th>Depends on</th></tr></thead><tbody>
                @foreach($tasks as $task)<tr><td><strong>{{ $numbers[$task->id]??$task->code }} &middot; {{ $task->title }}</strong><small>{{ $task->code }} &middot; {{ ucfirst($task->priority) }} priority &middot; {{ ucwords(str_replace('_',' ',$task->status)) }} &middot; {{ $task->progress }}%</small>@if($task->description)<p>{{ $task->description }}</p>@endif</td><td>{{ $task->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</td><td>{{ $task->starts_at?->format('d M Y') }}<small>{{ $task->starts_at?->format('h:i A') }}</small></td><td>{{ $task->ends_at?->format('d M Y') }}<small>{{ $task->ends_at?->format('h:i A') }}</small></td><td>{{ $task->dependencies->pluck('code')->join(', ') ?: 'None' }}</td></tr>@endforeach
            </tbody></table></div>@endif
        </section>
        <section class="ar-section"><h3>Project team</h3><div class="ar-team"><article><span><b>{{ $project->manager?->name }}</b><small>{{ $project->manager?->email }}</small></span><span class="ar-role">Project manager</span></article>
            @foreach($project->memberships->where('user_id','!=',$project->pm_user_id) as $membership)<article><span><b>{{ $membership->user?->name ?? 'Unavailable user' }}</b><small>{{ $membership->user?->email }}</small></span><span class="ar-role">{{ $membership->role?->name ?? 'Team member' }}</span></article>@endforeach
        </div></section>
        @if($files->isNotEmpty())<section class="ar-section"><h3>Project files</h3><div class="ar-files">@foreach($files as $file)<a href="{{ route('attachments.download',$file) }}">{{ $file->original_name }}<small>{{ $file->task?->title }}</small></a>@endforeach</div></section>@endif
    </div>
    <form method="post" action="{{ route('projects.respond',$project) }}" class="ar-response" data-approval-response>@csrf
        <div class="ar-reject-fields" data-reject-fields hidden><label for="approval-reject-reason">Reason for rejecting <span>*</span></label><textarea id="approval-reject-reason" name="decline_reason" rows="2" minlength="5" maxlength="1000" disabled placeholder="Tell PMO what needs to change or why you cannot take this assignment."></textarea><small>PMO will use this reason when reviewing the assignment.</small></div>
        <p class="ar-error" data-approval-error role="alert" hidden></p>
        <footer class="ar-footer"><p data-approval-note>{{ $project->approval_stage ? 'Accept to prepare the plan. Your team is notified only when you save and publish it.' : 'Accepting activates the project, saves its baseline and notifies the team.' }}</p><div><button type="button" class="btn btn-ghost" data-reject-back hidden>Back</button><button type="button" class="btn btn-outline ar-reject" data-show-rejection>Reject</button><button type="submit" class="btn btn-outline ar-reject" data-reject-submit data-decision="decline" hidden>Confirm rejection</button><button type="submit" class="btn btn-primary" data-accept-project data-decision="accept">Accept project</button></div></footer>
    </form>
</div>
