@extends('layouts.app')
@section('title', 'Project Role Permissions')
@section('content')
@include('projects._tabs')

<div class="page-head">
    <div>
        <a class="back-link" href="{{ route('projects.show', $project) }}">← Back to {{ $project->name }}</a>
        <h1>Project Role Permissions</h1>
        <p class="muted">Configure project-specific permission overrides for {{ $project->code }} ({{ $project->name }}).</p>
    </div>
</div>

<div class="card">
    <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px;">
        <b>Project Scope Overrides:</b> Overrides configured here apply <i>only to this specific project</i> and do not affect company-wide defaults. For example, granting <code>tasks.view_all</code> to Team Members allows them to view all tasks in this project plan without gaining access to edit unrelated tasks or access other company projects.
    </div>

    <form method="post" action="{{ route('roles.update', $project) }}">
        @csrf
        @method('PUT')

        <div class="table-wrap">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Permission</th>
                        @foreach($roles->where('slug', '!=', 'pm') as $role)
                            <th style="text-align: center; min-width: 130px;">
                                <div>{{ $role->name }}</div>
                                <small class="muted" style="font-weight: normal; font-size: 11px;">{{ $role->slug }}</small>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($available as $key => $label)
                        <tr>
                            <td>
                                <b>{{ $label }}</b>
                                <div style="font-size: 11px; color: #6B7280; font-family: monospace;">{{ $key }}</div>
                            </td>
                            @foreach($roles->where('slug', '!=', 'pm') as $role)
                                @php
                                    $current = $overrides->get($role->id)?->firstWhere('permission', $key);
                                    if ($current === null) {
                                        // check alias
                                        $alias = \App\Services\Access::ALIASES[$key] ?? null;
                                        if ($alias) {
                                            $current = $overrides->get($role->id)?->firstWhere('permission', $alias);
                                        }
                                    }
                                    $allowed = $current ? (bool)$current->allowed : in_array($key, $role->default_permissions ?? [], true);
                                @endphp
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           value="1" 
                                           name="permissions[{{ $role->id }}][{{ $key }}]" 
                                           @checked($allowed) 
                                           aria-label="{{ $label }} for {{ $role->name }}"
                                           style="width: 16px; height: 16px; accent-color: #315F58; cursor: pointer;">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="form-actions" style="margin-top: 24px;">
            <a href="{{ route('projects.show', $project) }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Project Permissions</button>
        </div>
    </form>
</div>

@endsection
