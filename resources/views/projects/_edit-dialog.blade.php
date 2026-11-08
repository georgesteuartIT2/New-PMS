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
        <div>
            <h2 id="project-editor-title">Edit project</h2>
            <p id="project-editor-subtitle">{{ $project->code }} <span aria-hidden="true">&middot;</span> {{ $project->name }}</p>
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
                    <label>Project template
                        <select name="project_template_id" form="project-details-edit-form">
                            <option value="">Blank project</option>
                            @foreach(\App\Models\ProjectTemplate::where(fn($q)=>$q->where('status','active')->orWhere('id',$project->project_template_id))->orderBy('name')->get() as $option)
                            <option value="{{ $option->id }}" @selected((string)$project->project_template_id===(string)$option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p>Change the linked template or choose Blank. Existing tasks, dates and progress are kept.</p>
                    <dl class="project-editor-context-list">
                        <div><dt>Project code</dt><dd>{{ $project->code }}</dd></div>
                        <div><dt>Subsidiary</dt><dd>{{ $project->subsidiary?->name ?? 'General Group' }}</dd></div>
                    </dl>
                </section>

                <section class="project-editor-context-card">
                    <h3>Project manager</h3>
                    @if($canTeam && in_array($project->status,['draft','pending','declined','on_hold'],true))
                    <label>Assigned manager<select name="pm_user_id" form="project-details-edit-form"><option value="">Not assigned</option>@foreach($users as $option)<option value="{{ $option->id }}" @selected($project->pm_user_id===$option->id)>{{ $option->name }}</option>@endforeach</select></label>
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
                        @foreach($editorPeople->reject(fn($person)=>$person['user']->id===$project->pm_user_id)->values() as $index=>$person)
                        <div class="project-editor-member" data-editor-member>
                            <label><span class="workspace-sr-only">Person</span><select data-member-user name="members[{{ $index }}][user_id]" form="project-details-edit-form" required>
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
                            <label><span class="workspace-sr-only">Person</span><select data-member-user form="project-details-edit-form" required><option value="">Choose person</option>@foreach($users->where('id','!=',$project->pm_user_id) as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
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
        <span data-editor-feedback role="status">Project details, template and team</span>
        <div>
            <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
            <button type="submit" form="project-details-edit-form" class="btn btn-primary">Save changes</button>
        </div>
    </footer>
</dialog>
@push('scripts')
<script defer src="{{ asset('js/project-editor.js') }}?v={{ filemtime(public_path('js/project-editor.js')) }}"></script>
@endpush