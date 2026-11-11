@php
    $editorPeople = collect();
    foreach ($project->memberships as $membership) {
        if ($membership->user) {
            $editorPeople->put($membership->user->id, ['user'=>$membership->user, 'role'=>$membership->role?->name ?? 'Team member']);
        }
    }
    if ($project->manager) {
        $editorPeople->put($project->manager->id, ['user'=>$project->manager, 'role'=>'Project manager']);
    }
    if ($canViewAllTasks ?? false) {
        foreach ($tasks->flatMap(fn($task)=>$task->assignees)->unique('id') as $assignedPerson) {
            if (!$editorPeople->has($assignedPerson->id)) {
                $editorPeople->put($assignedPerson->id, ['user'=>$assignedPerson, 'role'=>'Task assignee']);
            }
        }
    }
    $editorPeople = $editorPeople->sortBy(fn($person)=>($person['user']->id === $project->pm_user_id ? '0' : '1').mb_strtolower($person['user']->name))->values();
    $editorHasErrors = (old('_project_edit') && $errors->any()) || request()->boolean('edit');
    $editorStatuses = in_array($project->status, ['draft','pending','declined'], true)
        ? [$project->status => $project->status === 'pending' ? 'Awaiting PM approval' : ucwords($project->status)]
        : ['active'=>'Active','on_hold'=>'On hold','completed'=>'Completed','archived'=>'Archived'];
@endphp
<dialog id="project-edit-dialog" class="project-editor" aria-labelledby="project-editor-title" aria-describedby="project-editor-subtitle" data-reopen="{{ $editorHasErrors ? 'true' : 'false' }}">
    <header class="project-editor-head">
        <div class="project-editor-heading-wrap">
            <div class="project-editor-title-row">
                <h2 id="project-editor-title">Edit project</h2>
                <div class="project-editor-badges">
                    <span class="project-code-chip" title="Project code">
                        <svg class="project-chip-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
                        <span>{{ $project->code }}</span>
                    </span>
                    <span class="project-subsidiary-chip" title="Subsidiary">
                        <svg class="project-chip-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-3M9 9v.01M9 13v.01M9 17v.01"/></svg>
                        <span>{{ $project->subsidiary?->name ?? 'General Group' }}</span>
                    </span>
                </div>
            </div>
            <p id="project-editor-subtitle">{{ $project->name }}</p>
        </div>
        <button type="button" class="project-editor-close" data-close-dialog aria-label="Close edit project">&times;</button>
    </header>

    <div class="project-editor-body">
        @if($editorHasErrors)
            <div class="alert danger" role="alert"><b>Please check your changes.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="project-editor-grid">
            <section class="project-editor-details" aria-labelledby="project-edit-details-title">
                <div class="project-editor-section-head"><h3 id="project-edit-details-title">Project details</h3><span>Required fields marked *</span></div>
                <form method="post" action="{{ route('projects.update', $project) }}" id="project-details-edit-form" class="project-editor-fields">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_project_edit" value="1">
                    <label>Project name <span class="required">*</span>
                        <input name="name" value="{{ old('name', $project->name) }}" required maxlength="160" autofocus>
                    </label>
                    <label>Description <span class="project-editor-optional">Optional</span>
                        <textarea name="description" rows="3" maxlength="3000" placeholder="What will this project deliver?">{{ old('description', $project->description) }}</textarea>
                    </label>
                    <div class="project-editor-two-fields">
                        <label>Start date <span class="required">*</span>
                            <input type="date" name="starts_on" value="{{ old('starts_on', $project->starts_on->format('Y-m-d')) }}" required>
                        </label>
                        <label>Due date <span class="required">*</span>
                            <input type="date" name="ends_on" value="{{ old('ends_on', $project->ends_on->format('Y-m-d')) }}" min="{{ old('starts_on', $project->starts_on->format('Y-m-d')) }}" required>
                        </label>
                    </div>
                    @if($project->canManageBudget(auth()->user()))
                    <label>Planned budget (LKR) <span class="project-editor-optional">Optional</span>
                        <input type="number" name="planned_budget" value="{{ old('planned_budget',$project->planned_budget) }}" min="0" max="9999999999999.99" step="0.01" placeholder="Not set">
                        <small>Actual cost is calculated from recorded expenses.</small>
                    </label>
                    @endif
                    <label>Project status <span class="required">*</span>
                        <select name="status" required>
                            @foreach($editorStatuses as $value=>$label)
                                <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if(in_array($project->status, ['draft','pending','declined'], true))
                            <small>The project starts after the assigned manager accepts it.</small>
                        @endif
                    </label>
                </form>
            </section>

            <aside class="project-editor-context" aria-label="Project setup and ownership">
                <section class="project-editor-context-card" data-project-setup="{{ $project->project_template_id ? 'template' : 'blank' }}">
                    <h3>Project setup</h3>
                    <div class="project-template-readonly"><small>Original project template</small><strong>{{ $project->template?->name ?? 'Blank project' }}</strong><p>The original template is fixed. Manage the current plan in Tasks.</p></div>
                </section>

                <section class="project-editor-context-card">
                    <h3>Project manager</h3>
                    @if(!$project->approval_stage && $canTeam && ($project->status==='declined' || ($project->status==='draft' && !$project->pm_user_id)))
                    <label>Assigned manager<select data-directory-picker data-directory-project="{{ $project->id }}" name="pm_user_id" form="project-details-edit-form"><option value="">Not assigned</option>@foreach($users as $option)<option value="{{ $option->id }}" @selected($project->pm_user_id===$option->id)>{{ $option->name }}</option>@endforeach</select></label>
                    <p>A new manager must accept the project before it becomes active.</p>
                    @endif
                    @if($project->manager)
                        <div class="project-editor-person">
                            <span class="project-editor-avatar" aria-hidden="true">{{ Str::upper(Str::substr($project->manager->name, 0, 1)) }}</span>
                            <div><b>{{ $project->manager->name }}</b><small>{{ $project->manager->email }}</small></div>
                        </div>
                    @else
                        <p>No project manager assigned.</p>
                    @endif
                    <dl class="project-editor-context-list">
                        <div><dt>Created by</dt><dd>{{ $project->creator?->name ?? 'Not available' }}</dd></div>
                        <div><dt>Created on</dt><dd>{{ $project->created_at?->format('d M Y') ?? 'Not available' }}</dd></div>
                    </dl>
                </section>
            </aside>

            @if($canTeam || ($canViewAllTasks ?? false))
                <section class="project-editor-team" aria-labelledby="project-editor-team-title" data-editor-team-count="{{ $editorPeople->count() }}">
                    <div class="project-editor-team-head">
                        <div><h3 id="project-editor-team-title">Assigned team <span>{{ $editorPeople->count() }}</span></h3><p>People assigned to this project and its tasks.</p></div>
                        @if($canTeam)<button type="button" class="btn btn-outline btn-sm" data-editor-add-member>+ Add person</button>@endif
                    </div>
                    @if($canTeam)
                        <input type="hidden" name="edit_team" value="1" form="project-details-edit-form">
                        <p class="muted">Removing a person also removes their task assignments. The manager is managed separately.</p>
                        <div data-editor-members>
                        @foreach($editorPeople->reject(fn($person)=>$person['user']->id===$project->pm_user_id || ($project->approval_stage && $person['user']->id===$project->owner_user_id))->values() as $index=>$person)
                        <div class="project-editor-member" data-editor-member>
                            <label><span class="workspace-sr-only">Person</span><select data-directory-picker data-directory-project="{{ $project->id }}" data-member-user name="members[{{ $index }}][user_id]" form="project-details-edit-form" required>
                            @foreach($users->merge(collect([$person['user']]))->unique('id') as $option)<option value="{{ $option->id }}" @selected($option->id===$person['user']->id)>{{ $option->name }}</option>@endforeach
                            </select></label>
                            <label><span class="workspace-sr-only">Project role</span><select data-member-role name="members[{{ $index }}][project_role_id]" form="project-details-edit-form" required><option value="">Choose role</option>
                            @foreach($roles->where('slug','!=','pm') as $role)<option value="{{ $role->id }}" @selected($project->memberships->firstWhere('user_id',$person['user']->id)?->project_role_id===$role->id)>{{ $role->name }}</option>@endforeach
                            </select></label>
                            <button type="button" class="btn btn-ghost" data-editor-remove-member aria-label="Remove person">&times;</button>
                        </div>
                        @endforeach
                        </div>
                        <template data-editor-member-template><div class="project-editor-member" data-editor-member>
                            <label><span class="workspace-sr-only">Person</span><select data-directory-picker data-directory-project="{{ $project->id }}" data-member-user form="project-details-edit-form" required><option value="">Choose person</option>@foreach($users->where('id','!=',$project->pm_user_id) as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
                            <label><span class="workspace-sr-only">Project role</span><select data-member-role form="project-details-edit-form" required><option value="">Choose role</option>@foreach($roles->where('slug','!=','pm') as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label>
                            <button type="button" class="btn btn-ghost" data-editor-remove-member aria-label="Remove person">&times;</button>
                        </div></template>
                    @endif
                    @if($editorPeople->count() > 5)
                        <label class="project-editor-team-search"><span class="workspace-sr-only">Find an assigned person</span><input type="search" placeholder="Find a person..." data-editor-team-search></label>
                    @endif
                    <div class="project-editor-people" @if($canTeam) hidden @endif>
                        @forelse($editorPeople as $person)
                            <div class="project-editor-team-person" data-editor-person="{{ $person['user']->id }}" data-person-name="{{ mb_strtolower($person['user']->name . ' ' . $person['user']->email) }}">
                                <span class="project-editor-avatar" aria-hidden="true">{{ Str::upper(Str::substr($person['user']->name, 0, 1)) }}</span>
                                <div class="project-editor-person-copy"><b>{{ $person['user']->name }}</b><small>{{ $person['user']->email }}</small></div>
                                <span class="project-editor-role">{{ $person['role'] }}</span>
                            </div>
                        @empty
                            <p class="project-editor-empty">No people assigned yet.</p>
                        @endforelse
                    </div>
                    <p class="project-editor-empty" data-editor-no-people hidden>No matching people.</p>
                </section>
            @endif
            @if($project->baseline_captured_at)
                <details class="project-editor-baseline">
                    <summary>Original schedule</summary>
                    <p>{{ $project->baseline_starts_on?->format('d M Y') ?? 'Not recorded' }} &ndash; {{ $project->baseline_ends_on?->format('d M Y') ?? 'Not recorded' }} <span>&middot; Baseline saved {{ $project->baseline_captured_at->format('d M Y') }}</span></p>
                </details>
            @endif
        </div>
    </div>

    <footer class="project-editor-footer">
        <span data-editor-feedback role="status">Project details and assigned team</span>
        <div>
            <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
            <button type="submit" form="project-details-edit-form" class="btn btn-primary">Save changes</button>
        </div>
    </footer>
</dialog>
@push('scripts')
<script defer src="{{ asset('js/project-editor.js') }}?v={{ filemtime(public_path('js/project-editor.js')) }}"></script>
@endpush