@php
    $roleProject = $roleProject ?? null;
    $optionalRoleGroups = collect(app(\App\Services\Access::class)->catalogGrouped())->except('company')->all();
@endphp
<details class="flow-optional-section flow-role-card" data-optional-roles data-project-id="{{ $roleProject?->id }}" data-role-url="{{ route('roles.quick') }}" data-draft-key="project-roles-{{ auth()->id() }}-{{ $roleProject?->id ?? 'new' }}" @if(old('members')) open @endif>
    @if($compactSetup ?? false)
    <summary class="initiate-setup-row"><span class="initiate-setup-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v3"/></svg></span><span class="initiate-setup-copy"><b>Project roles</b><small data-role-summary>No additional people assigned</small></span><span data-role-count hidden></span><span class="initiate-setup-action"><span data-setup-action>Add</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 5 7 7-7 7"/></svg></span></summary>
    @else
    <summary class="flow-role-card-summary">
        <div class="flow-role-card-title-group">
            <span class="flow-role-card-icon" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </span>
            <span class="flow-role-card-title">{{ $roleSectionTitle ?? 'Project roles' }}</span>
            <span class="flow-role-card-badge">Optional</span>
        </div>
        <div class="flow-role-card-meta">
            <span data-role-count class="flow-role-count-pill"></span>
            <span class="flow-role-chevron" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </span>
        </div>
    </summary>
    @endif

    <div class="flow-role-card-body">
        <div class="flow-option-hint-banner">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flow-hint-icon" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <p>Search a system user or add someone from Microsoft, then choose their project role. You can also assign people directly to tasks later.</p>
        </div>

        @if($roleProject)
            @php 
                $existingRoleMembers = $roleProject->memberships->reject(fn($m) => in_array($m->user_id, [$roleProject->pm_user_id, $roleProject->owner_user_id], true)); 
            @endphp
            @if($existingRoleMembers->isNotEmpty())
                <div class="flow-existing-roles-card">
                    <small>Already assigned to this project:</small>
                    <div class="flow-existing-chips">
                        @foreach($existingRoleMembers as $membership)
                            <span class="flow-existing-chip">
                                <span class="flow-chip-avatar">{{ Str::upper(Str::substr($membership->user?->name ?? 'U', 0, 1)) }}</span>
                                <b>{{ $membership->user?->name }}</b>
                                <small>&middot; {{ $membership->role?->name }}</small>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        <div data-role-rows class="flow-role-rows-wrap">
            @foreach(old('members',[]) as $i=>$member)
                <div class="flow-role-row" data-role-row>
                    <label class="flow-role-col-person">
                        <span class="flow-col-label">Person</span>
                        <select name="members[{{ $i }}][user_id]" data-role-person data-directory-picker data-directory-project="{{ $roleProject?->id }}">
                            <option value="">Search a person</option>
                            @foreach($users as $person)
                                <option value="{{ $person->id }}" @selected(($member['user_id']??'')==$person->id)>{{ $person->name }} ({{ $person->email }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flow-role-col-role">
                        <span class="flow-col-label">Project role</span>
                        <select name="members[{{ $i }}][project_role_id]" data-role-choice class="flow-select-role">
                            <option value="">Choose a role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected(($member['project_role_id']??'')==$role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" class="flow-role-remove-btn" data-remove-role-row aria-label="Remove role assignment" title="Remove assignment">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>

        <template data-role-row-template>
            <div class="flow-role-row" data-role-row>
                <label class="flow-role-col-person">
                    <span class="flow-col-label">Person</span>
                    <select data-role-person data-directory-picker data-directory-project="{{ $roleProject?->id }}">
                        <option value="">Search a person</option>
                        @foreach($users as $person)
                            <option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="flow-role-col-role">
                    <span class="flow-col-label">Project role</span>
                    <select data-role-choice class="flow-select-role">
                        <option value="">Choose a role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="flow-role-remove-btn" data-remove-role-row aria-label="Remove role assignment" title="Remove assignment">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </template>

        <div class="flow-role-actions">
            <button type="button" class="flow-btn-add-role" data-add-role-row>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Assign project role</span>
            </button>
            <button type="button" class="flow-btn-custom-role" data-show-new-role>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Create custom role</span>
            </button>
        </div>

        <section data-new-role-panel class="flow-new-role-card" hidden>
            <div class="flow-new-role-head">
                <span class="flow-new-role-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                <div>
                    <h4>Create Custom Project Role</h4>
                    <p>Define permissions for this role across the project.</p>
                </div>
            </div>
            <label class="flow-new-role-input-wrap">
                <span>Role name <span style="color:#DC2626;">*</span></span>
                <input type="text" maxlength="80" data-new-role-name placeholder="e.g. QA Lead or Business Analyst">
            </label>
            <div class="flow-new-role-perms">
                <span class="flow-col-label">Permissions</span>
                @include('roles._permission-list',['permissionGroups'=>$optionalRoleGroups,'selectedPermissions'=>['project.view'],'lockedPermissions'=>['project.view'],'permissionInputName'=>'','permissionInputClass'=>'','permissionCountId'=>null])
            </div>
            <div class="flow-role-actions" style="margin-top:14px;padding-top:14px;border-top:1px solid #E2E8F0;">
                <button type="button" class="btn btn-primary btn-sm" data-save-new-role>Save &amp; apply role</button>
                <button type="button" class="btn btn-outline btn-sm" data-cancel-new-role>Cancel</button>
            </div>
        </section>

        <p data-roles-feedback role="status" class="flow-roles-feedback"></p>
    </div>
</details>
<link rel="stylesheet" href="{{ asset('css/permissions.css') }}?v={{ filemtime(public_path('css/permissions.css')) }}">
<script defer src="{{ asset('js/permission-picker.js') }}?v={{ filemtime(public_path('js/permission-picker.js')) }}"></script>
<script defer src="{{ asset('js/flow-optional-roles.js') }}?v={{ filemtime(public_path('js/flow-optional-roles.js')) }}"></script>
