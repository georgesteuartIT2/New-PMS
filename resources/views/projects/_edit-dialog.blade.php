@php
    $editorPeople = collect();
    foreach ($project->memberships as $membership) {
        if ($membership->user) {
            $editorPeople->put($membership->user->id, ['user'=>$membership->user, 'role'=>$membership->role?->name ?? 'Team member']);
        }
    }
    if ($project->owner) {
        $editorPeople->put($project->owner->id, ['user'=>$project->owner, 'role'=>'Project Owner']);
    }
    if ($project->manager) {
        $editorPeople->put($project->manager->id, ['user'=>$project->manager, 'role'=>'Project Manager']);
    }
    if ($canViewAllTasks ?? false) {
        foreach ($tasks->flatMap(fn($task)=>$task->assignees)->unique('id') as $assignedPerson) {
            if (!$editorPeople->has($assignedPerson->id)) {
                $editorPeople->put($assignedPerson->id, ['user'=>$assignedPerson, 'role'=>'Task assignee']);
            }
        }
    }
    $editorPeople = $editorPeople->sortBy(fn($person)=>(
        $person['user']->id === $project->owner_user_id ? '0' :
        ($person['user']->id === $project->pm_user_id ? '1' : '2')
    ).mb_strtolower($person['user']->name))->values();

    $editorHasErrors = (old('_project_edit') && $errors->any()) || request()->boolean('edit');

    $isPlanningStage = $project->approval_stage === 'planning';
    $isOwnerApprovalStage = $project->approval_stage === 'owner';
    $isPmApprovalStage = $project->approval_stage === 'pm';

    $editorStatuses = match(true) {
        $isOwnerApprovalStage => ['pending' => 'Awaiting Owner approval'],
        $isPmApprovalStage => ['pending' => 'Awaiting PM acceptance'],
        $isPlanningStage => ['active' => 'Planning (Unpublished)'],
        $project->status === 'declined' => ['declined' => 'Declined'],
        $project->status === 'draft' => ['draft' => 'Draft'],
        $project->status === 'pending' => ['pending' => 'Awaiting PM approval'],
        default => ['active'=>'Active','on_hold'=>'On hold','completed'=>'Completed','archived'=>'Archived'],
    };

    $leadershipIds = array_filter([$project->pm_user_id, $project->owner_user_id]);
    $teamUsers = $users->whereNotIn('id', $leadershipIds);
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
                    @if($isPlanningStage)
                        <span class="project-stage-chip stage-planning" title="Lifecycle Stage">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            <span>Planning stage</span>
                        </span>
                    @elseif($isOwnerApprovalStage)
                        <span class="project-stage-chip stage-review" title="Lifecycle Stage">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>Owner review</span>
                        </span>
                    @elseif($isPmApprovalStage)
                        <span class="project-stage-chip stage-review" title="Lifecycle Stage">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>PM review</span>
                        </span>
                    @endif
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
                        <select name="status" required @if($isPlanningStage || $isOwnerApprovalStage || $isPmApprovalStage) style="background:#F8FAFC;" @endif>
                            @foreach($editorStatuses as $value=>$label)
                                <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if($isPlanningStage)
                            <small style="color:#0D5C46;display:flex;align-items:flex-start;gap:6px;margin-top:6px;line-height:1.4;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <span>Project plan is being prepared. Click <b>Save & notify team</b> on the workspace banner to publish the plan and activate the project.</span>
                            </small>
                        @elseif($isOwnerApprovalStage)
                            <small>Awaiting Project Owner review and PM assignment.</small>
                        @elseif($isPmApprovalStage)
                            <small>Awaiting assigned Project Manager to accept or decline the project.</small>
                        @elseif(in_array($project->status, ['draft','pending','declined'], true))
                            <small>The project starts after the assigned manager accepts it.</small>
                        @endif
                    </label>
                </form>
            </section>

            <aside class="project-editor-context" aria-label="Project setup and governance">
                <section class="project-editor-context-card" data-project-setup="{{ $project->project_template_id ? 'template' : 'blank' }}">
                    <h3>Project setup & plan</h3>
                    <div class="project-template-readonly">
                        <small>Plan source</small>
                        <strong>{{ $project->template?->name ?? 'Blank project plan' }}</strong>
                        @if($isPlanningStage)
                            <p style="color:#0D5C46;font-weight:500;margin-top:6px;">Plan setup in progress. Tasks and team assignments will be published when you click <b>Save & notify team</b>.</p>
                        @elseif($project->plan_published_at)
                            <p style="color:#1E534B;font-size:11px;margin-top:6px;">Plan published on {{ $project->plan_published_at->format('d M Y') }}.</p>
                        @else
                            <p>The original template structure is fixed. Manage the current plan in Tasks.</p>
                        @endif
                    </div>
                </section>

                <section class="project-editor-context-card" aria-label="Project leadership">
                    <h3>Project leadership</h3>

                    <!-- Project Owner Block -->
                    <div class="project-editor-leadership-item" style="margin-bottom:14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748B;">Project Owner</span>
                            @if($project->owner)
                                <span style="display:inline-flex;padding:2px 7px;font-size:10px;font-weight:600;border-radius:4px;background:#EDF4F1;color:#1E534B;">Assigned</span>
                            @else
                                <span style="display:inline-flex;padding:2px 7px;font-size:10px;font-weight:500;border-radius:4px;background:#F1F5F9;color:#64748B;">Direct PM flow</span>
                            @endif
                        </div>
                        @if($project->owner)
                            <div class="project-editor-person">
                                <span class="project-editor-avatar" style="background:#EBF3F9;color:#0369A1;border-color:#BAE6FD;" aria-hidden="true">{{ Str::upper(Str::substr($project->owner->name, 0, 1)) }}</span>
                                <div>
                                    <b>{{ $project->owner->name }}</b>
                                    <small>{{ $project->owner->email }}</small>
                                </div>
                            </div>
                        @else
                            <p style="font-size:11.5px;color:#64748B;margin:4px 0 0;">Direct PM assignment (No Owner assigned).</p>
                        @endif
                    </div>

                    <!-- Project Manager Block -->
                    <div class="project-editor-leadership-item" style="padding-top:12px;border-top:1px solid #E9EEEA;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748B;">Project Manager</span>
                            @if($isPlanningStage)
                                <span style="display:inline-flex;padding:2px 7px;font-size:10px;font-weight:600;border-radius:4px;background:#EBF7EE;color:#1E6B38;">Accepted & planning</span>
                            @elseif($isPmApprovalStage)
                                <span style="display:inline-flex;padding:2px 7px;font-size:10px;font-weight:600;border-radius:4px;background:#FFF7ED;color:#C2410C;">Awaiting acceptance</span>
                            @elseif($project->status === 'declined')
                                <span style="display:inline-flex;padding:2px 7px;font-size:10px;font-weight:600;border-radius:4px;background:#FEF2F2;color:#991B1B;">Declined</span>
                            @elseif($project->manager)
                                <span style="display:inline-flex;padding:2px 7px;font-size:10px;font-weight:600;border-radius:4px;background:#EDF4F1;color:#1E534B;">Assigned</span>
                            @endif
                        </div>
                        @if(!$project->approval_stage && $canTeam && ($project->status==='declined' || ($project->status==='draft' && !$project->pm_user_id)))
                            <label>Assigned manager
                                <select data-directory-picker data-directory-project="{{ $project->id }}" name="pm_user_id" form="project-details-edit-form">
                                    <option value="">Not assigned</option>
                                    @foreach($users as $option)
                                        <option value="{{ $option->id }}" @selected($project->pm_user_id===$option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <p>A new manager must accept the project before it becomes active.</p>
                        @endif
                        @if($project->manager)
                            <div class="project-editor-person">
                                <span class="project-editor-avatar" aria-hidden="true">{{ Str::upper(Str::substr($project->manager->name, 0, 1)) }}</span>
                                <div>
                                    <b>{{ $project->manager->name }}</b>
                                    <small>{{ $project->manager->email }}</small>
                                </div>
                            </div>
                        @else
                            <p style="font-size:11.5px;color:#64748B;margin:4px 0 0;">No project manager assigned.</p>
                        @endif
                    </div>

                    <p style="font-size:11px;color:#64748B;margin:12px 0 0;padding-top:10px;border-top:1px dashed #E2E8F0;line-height:1.4;">
                        Project Owner and Project Manager roles are governed through the approval workflow.
                    </p>

                    <dl class="project-editor-context-list">
                        <div><dt>Created by</dt><dd>{{ $project->creator?->name ?? 'PMO Admin' }}</dd></div>
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
                        <p class="muted">Removing a person also removes their task assignments. The Project Owner and Project Manager are managed separately through the governance workflow.</p>
                        <div data-editor-members>
                        @foreach($editorPeople->reject(fn($person)=>in_array($person['user']->id, $leadershipIds, true))->values() as $index=>$person)
                        <div class="project-editor-member" data-editor-member>
                            <label><span class="workspace-sr-only">Person</span><select data-directory-picker data-directory-project="{{ $project->id }}" data-member-user name="members[{{ $index }}][user_id]" form="project-details-edit-form" required>
                            @foreach($users->whereNotIn('id', array_filter([$project->pm_user_id, $project->owner_user_id]))->merge(collect([$person['user']]))->unique('id') as $option)<option value="{{ $option->id }}" @selected($option->id===$person['user']->id)>{{ $option->name }}</option>@endforeach
                            </select></label>
                            <label><span class="workspace-sr-only">Project role</span><select data-member-role name="members[{{ $index }}][project_role_id]" form="project-details-edit-form" required><option value="">Choose role</option>
                            @foreach($roles->where('slug','!=','pm') as $role)<option value="{{ $role->id }}" @selected($project->memberships->firstWhere('user_id',$person['user']->id)?->project_role_id===$role->id)>{{ $role->name }}</option>@endforeach
                            </select></label>
                            <button type="button" class="btn btn-ghost" data-editor-remove-member aria-label="Remove person">&times;</button>
                        </div>
                        @endforeach
                        </div>
                        <template data-editor-member-template><div class="project-editor-member" data-editor-member>
                            <label><span class="workspace-sr-only">Person</span><select data-directory-picker data-directory-project="{{ $project->id }}" data-member-user form="project-details-edit-form" required><option value="">Choose person</option>@foreach($teamUsers as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
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
