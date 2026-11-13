@php
    $newFlow = (bool) $project->approval_stage;
    $scheduleLater = $newFlow && !$project->plan_initialized;
    $teamMembers = $project->memberships->where('user_id', '!=', $project->pm_user_id)->filter(fn($m) => $m->user)->unique('user_id');
    $requester = $project->owner_user_id && $project->owner && $project->owner->id !== auth()->id() ? $project->owner : $project->creator;
    $planLabel = $project->template?->name ?? 'Blank project';
    $workingDays = (!$scheduleLater && $project->starts_on && $project->ends_on) ? max(1, (int) $project->starts_on->diffInWeekdays($project->ends_on) + 1) : null;
@endphp
<div class="approval-request-content" data-approval-content>
    <header class="ar-head">
        <span class="ar-head-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></span>
        <div class="ar-head-copy">
            <span class="ar-eyebrow">{{ $requester?->name ?? 'PMO' }} invited you to manage</span>
            <h2 id="approval-request-title" tabindex="-1">{{ $project->name }}</h2>
            <p>{{ $project->code }} &middot; {{ $project->subsidiary?->name ?? 'General Group' }}</p>
        </div>
        <button type="button" class="ar-close" data-close-approval aria-label="Close review"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </header>

    <div class="ar-body">
        <dl class="ar-facts">
            <div>
                <dt>Schedule</dt>
                @if($scheduleLater)
                    <dd>You set it after accepting</dd>
                @else
                    <dd>{{ $project->starts_on?->format('d M') }} &ndash; {{ $project->ends_on?->format('d M Y') }}@if($workingDays)<small>{{ $workingDays }} working {{ \Illuminate\Support\Str::plural('day', $workingDays) }}</small>@endif</dd>
                @endif
            </div>
            <div>
                <dt>Starting plan</dt>
                <dd>{{ $planLabel }}<small>{{ $leafCount ? $leafCount.' '.\Illuminate\Support\Str::plural('task', $leafCount).' ready' : 'No tasks yet' }}</small></dd>
            </div>
            <div>
                <dt>Requested</dt>
                <dd>{{ $invitation->created_at->format('d M Y') }}<small>{{ $invitation->created_at->diffForHumans() }}</small></dd>
            </div>
        </dl>

        @if($project->description)
            <div class="ar-description"><h4>Scope / Description</h4><p>{{ $project->description }}</p></div>
        @endif

        @if($newFlow)
            <div class="ar-next" aria-label="What happens after you accept">
                <span class="ar-next-label">After you accept</span>
                <ol>
                    <li><b>1</b>Choose a blank plan or template</li>
                    <li><b>2</b>Add tasks &amp; assign people</li>
                    <li><b>3</b>Publish &amp; notify the team</li>
                </ol>
            </div>
        @endif

        @if($tasks->isNotEmpty())
            <details class="ar-more">
                <summary><span>Task plan</span><em>{{ $leafCount }} executable tasks</em></summary>
                <ul class="ar-task-list">
                    @foreach($tasks as $task)
                        <li class="{{ $task->children_count ? 'is-parent' : '' }}">
                            <div><strong>{{ $numbers[$task->id] ?? $task->code }} &middot; {{ $task->title }}</strong>@if($task->description)<p>{{ $task->description }}</p>@endif</div>
                            <span class="ar-task-who">{{ $task->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
                            <span class="ar-task-when">{{ $task->starts_at?->format('d M') }} &ndash; {{ $task->ends_at?->format('d M') }}</span>
                        </li>
                    @endforeach
                </ul>
                <a class="ar-more-link" href="{{ route('projects.show',$project) }}#gantt">Open full project &rarr;</a>
            </details>
        @endif

        @if($teamMembers->isNotEmpty())
            <details class="ar-more">
                <summary><span>Project team</span><em>{{ $teamMembers->count() }} {{ \Illuminate\Support\Str::plural('person', $teamMembers->count()) }}</em></summary>
                <ul class="ar-team">
                    @foreach($teamMembers as $membership)
                        <li><span class="ar-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($membership->user->name,0,1)) }}</span><span class="ar-person"><b>{{ $membership->user->name }}</b><small>{{ $membership->user->email }}</small></span><span class="ar-role">{{ $membership->role?->name ?? 'Team member' }}</span></li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if($files->isNotEmpty())
            <details class="ar-more">
                <summary><span>Project files</span><em>{{ $files->count() }}</em></summary>
                <div class="ar-files">@foreach($files as $file)<a href="{{ route('attachments.download',$file) }}">{{ $file->original_name }}<small>{{ $file->task?->title }}</small></a>@endforeach</div>
            </details>
        @endif
    </div>

    <form method="post" action="{{ route('projects.respond',$project) }}" class="ar-response" data-approval-response>@csrf
        <div class="ar-reject-fields" data-reject-fields hidden>
            <label for="approval-reject-reason">Why are you rejecting? <span>*</span></label>
            <div class="ar-reason-chips" role="group" aria-label="Quick reasons">
                @foreach(['I don\'t have capacity right now','Someone else is a better fit','I need more details about the scope','The timeline is not realistic'] as $quickReason)
                    <button type="button" class="ar-chip" data-quick-reason="{{ $quickReason }}">{{ $quickReason }}</button>
                @endforeach
            </div>
            <textarea id="approval-reject-reason" name="decline_reason" rows="2" minlength="5" maxlength="1000" disabled placeholder="Pick a reason above or write your own..."></textarea>
            <small>{{ $newFlow ? 'The Project Owner and PMO will see this and can reassign the project.' : 'PMO will use this reason when reviewing the assignment.' }}</small>
        </div>
        <p class="ar-error" data-approval-error role="alert" hidden></p>
        <footer class="ar-footer">
            <p data-approval-note>{{ $newFlow ? 'Nobody is notified until you publish the plan.' : 'Accepting activates the project, saves its baseline and notifies the team.' }}</p>
            <div>
                <button type="button" class="btn btn-ghost" data-reject-back hidden>Back</button>
                <button type="button" class="btn btn-outline ar-reject" data-show-rejection>Reject</button>
                <button type="submit" class="btn ar-reject-confirm" data-reject-submit data-decision="decline" hidden>Confirm rejection</button>
                <button type="submit" class="btn btn-primary ar-accept" data-accept-project data-decision="accept">Accept project<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
            </div>
        </footer>
    </form>
</div>
