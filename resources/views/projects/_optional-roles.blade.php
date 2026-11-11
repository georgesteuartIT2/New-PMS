@php
 $roleProject=$roleProject??null;
 $optionalRoleGroups=collect(app(\App\Services\Access::class)->catalogGrouped())->except('company')->all();
@endphp
<details class="flow-optional-section" data-optional-roles data-project-id="{{ $roleProject?->id }}" data-role-url="{{ route('roles.quick') }}" data-draft-key="project-roles-{{ auth()->id() }}-{{ $roleProject?->id ?? 'new' }}" @if(old('members')) open @endif>
<summary><span>Project roles <small>Optional</small></span><span data-role-count></span></summary>
<p class="flow-option-hint">Add people and their project roles. You can also do this later.</p>
@if($roleProject)
@php $existingRoleMembers=$roleProject->memberships->reject(fn($m)=>in_array($m->user_id,[$roleProject->pm_user_id,$roleProject->owner_user_id],true)); @endphp
@if($existingRoleMembers->isNotEmpty())<div class="flow-existing-roles"><small>Already assigned</small>@foreach($existingRoleMembers as $membership)<span>{{ $membership->user?->name }} &middot; {{ $membership->role?->name }}</span>@endforeach</div>@endif
@endif
<div data-role-rows>
@foreach(old('members',[]) as $i=>$member)
<div class="flow-role-row" data-role-row><label>Person<select name="members[{{ $i }}][user_id]" data-role-person data-directory-picker data-directory-project="{{ $roleProject?->id }}"><option value="">Search a person</option>@foreach($users as $person)<option value="{{ $person->id }}" @selected(($member['user_id']??'')==$person->id)>{{ $person->name }} ({{ $person->email }})</option>@endforeach</select></label><label>Project role<select name="members[{{ $i }}][project_role_id]" data-role-choice><option value="">Choose a role</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(($member['project_role_id']??'')==$role->id)>{{ $role->name }}</option>@endforeach</select></label><button type="button" class="btn btn-ghost" data-remove-role-row aria-label="Remove unsaved role assignment">&times;</button></div>
@endforeach
</div>
<template data-role-row-template><div class="flow-role-row" data-role-row><label>Person<select data-role-person data-directory-picker data-directory-project="{{ $roleProject?->id }}"><option value="">Search a person</option>@foreach($users as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>@endforeach</select></label><label>Project role<select data-role-choice><option value="">Choose a role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label><button type="button" class="btn btn-ghost" data-remove-role-row aria-label="Remove unsaved role assignment">&times;</button></div></template>
<div class="flow-role-actions"><button type="button" class="btn btn-outline btn-sm" data-add-role-row>+ Assign project role</button><button type="button" class="text-action" data-show-new-role>+ Create custom role</button></div>
<section data-new-role-panel class="flow-new-role" hidden><h3>New project role</h3><label>Role name<input type="text" maxlength="80" data-new-role-name placeholder="e.g. QA Lead or Business Analyst"></label><p>Check what this role can do within a project.</p>
@include('roles._permission-list',['permissionGroups'=>$optionalRoleGroups,'selectedPermissions'=>['project.view'],'lockedPermissions'=>['project.view'],'permissionInputName'=>'','permissionInputClass'=>'','permissionCountId'=>null])
<div class="flow-role-actions"><button type="button" class="btn btn-primary btn-sm" data-save-new-role>Create role</button><button type="button" class="btn btn-outline btn-sm" data-cancel-new-role>Cancel</button></div></section>
<p data-roles-feedback role="status" class="flow-option-hint"></p>
</details>
<link rel="stylesheet" href="{{ asset('css/permissions.css') }}?v={{ filemtime(public_path('css/permissions.css')) }}">
<script defer src="{{ asset('js/permission-picker.js') }}?v={{ filemtime(public_path('js/permission-picker.js')) }}"></script>
<script defer src="{{ asset('js/flow-optional-roles.js') }}?v={{ filemtime(public_path('js/flow-optional-roles.js')) }}"></script>
