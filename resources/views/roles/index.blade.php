@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('content')

<!-- Page Header -->
<div class="page-head">
    <div>
        <h1>Roles & Permissions</h1>
        <p class="muted">Configure role definitions, company-wide authorities, and default project permissions.</p>
    </div>
    <div class="inline-actions">
        <button type="button" class="btn btn-primary" onclick="toggleNewRoleForm()">+ New Role</button>
    </div>
</div>

<!-- Inline New Role Form (Expands upon click, no disruptive modals) -->
<div id="new-role-card" class="card" style="display: none; margin-bottom: 24px; border: 1px solid #315F58; background: #FAFDFB;">
    <div class="card-head" style="margin-bottom: 16px;">
        <div>
            <h2 style="font-size: 16px; margin: 0;">Create Custom Project Role</h2>
            <p class="muted" style="margin: 2px 0 0 0; font-size: 13px;">Define a new custom role. You can assign default permissions immediately after creating it.</p>
        </div>
        <button type="button" class="btn btn-outline btn-xs" onclick="toggleNewRoleForm()">Cancel</button>
    </div>
    <form method="post" action="{{ route('role-directory.store') }}">
        @csrf
        <div style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <label style="flex: 1; min-width: 260px; font-size: 13px; font-weight: 500;">
                Role Name <span style="color: var(--danger);">*</span>
                <input type="text" name="name" required placeholder="e.g. Quality Assurance Lead" style="margin-top: 6px; width: 100%;">
            </label>
            <button type="submit" class="btn btn-primary" style="height: 38px;">Create Role</button>
        </div>
    </form>
</div>

<!-- Main RBAC Two-Column Layout -->
<div class="rbac-grid">
    <!-- Left Column: Searchable Roles List -->
    <aside class="rbac-sidebar card">
        <div class="rbac-sidebar-head">
            <h2 style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin: 0 0 12px 0;">Available Roles</h2>
            <form method="get" action="{{ route('role-directory.index') }}" class="roles-search-form">
                <input type="text" name="q" value="{{ $search }}" placeholder="Filter roles..." class="roles-search-input" oninput="filterRolesLive(this.value)">
                @if($search)
                    <a href="{{ route('role-directory.index') }}" class="clear-search-link">Clear</a>
                @endif
            </form>
        </div>

        <div class="roles-nav-list" id="roles-nav-list">
            @forelse($roles as $role)
                @php
                    $isActive = $selectedRole && $selectedRole->id === $role->id;
                @endphp
                <a href="{{ route('role-directory.index', ['role' => $role->id, 'q' => $search]) }}" 
                   class="role-item-link {{ $isActive ? 'active' : '' }}" 
                   data-role-name="{{ strtolower($role->name) }}"
                   data-role-slug="{{ strtolower($role->slug) }}">
                    <div class="role-item-info">
                        <span class="role-item-name">{{ $role->name }}</span>
                        <span class="role-item-meta">
                            {{ $role->slug === 'pm' ? 'Project Lead' : ($role->memberships_count . ' ' . ($role->memberships_count === 1 ? 'member' : 'members')) }}
                        </span>
                    </div>
                    <span class="status-pill {{ $role->is_system ? 'pill-neutral' : 'pill-success' }}" style="font-size: 11px; padding: 2px 8px;">
                        {{ $role->is_system ? 'Built-in' : 'Custom' }}
                    </span>
                </a>
            @empty
                <div class="empty-state" style="padding: 24px 12px; font-size: 13px;">No roles match your search.</div>
            @endforelse
        </div>
    </aside>

    <!-- Right Column: Role Details & Permissions Editor -->
    <main class="rbac-editor card">
        @if($selectedRole)
            <!-- Role Header & Context -->
            <div class="role-editor-top">
                <div class="role-editor-titles">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                        <h2 style="margin: 0; font-size: 20px;">{{ $selectedRole->name }}</h2>
                        <span class="status-pill {{ $selectedRole->is_system ? 'pill-neutral' : 'pill-success' }}" style="font-size: 11px; padding: 2px 8px;">
                            {{ $selectedRole->is_system ? 'Built-in System Role' : 'Custom Project Role' }}
                        </span>
                        <code style="font-size: 11px; background: #F3F4F6; padding: 2px 6px; border-radius: 4px; color: #4B5563;">{{ $selectedRole->slug }}</code>
                    </div>
                    <p class="muted" style="font-size: 13px; margin: 0;">
                        @if($selectedRole->is_system)
                            Built-in role identifier is protected. Permissions are fully configurable by PMO administrators.
                        @else
                            Custom role. You can rename this role and configure its default permissions policy.
                        @endif
                    </p>
                </div>

                @unless($selectedRole->is_system)
                    <form method="post" action="{{ route('role-directory.delete', $selectedRole) }}" onsubmit="return confirm('Are you sure you want to delete this custom role? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-xs" style="color: var(--danger); border-color: #FECACA;">Delete Role</button>
                    </form>
                @endunless
            </div>

            <!-- Role Scope & Affected Users Impact Warning -->
            <div class="role-impact-callout" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px 16px; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                    <div>
                        <b>Active Impact:</b> Affects <b>{{ $affectedUsersCount }}</b> {{ $affectedUsersCount === 1 ? 'user' : 'users' }} across <b>{{ $affectedProjectsCount }}</b> {{ $affectedProjectsCount === 1 ? 'project' : 'projects' }}.
                    </div>
                    <span class="muted" style="font-size: 12px;">Changes take effect immediately upon saving.</span>
                </div>
            </div>

            <!-- Permissions Editor Form -->
            <form method="post" action="{{ route('role-directory.update', $selectedRole) }}">
                @csrf
                @method('PUT')

                @unless($selectedRole->is_system)
                    <div style="margin-bottom: 24px;">
                        <label style="font-size: 13px; font-weight: 500;">
                            Role Display Name <span style="color: var(--danger);">*</span>
                            <input type="text" name="name" value="{{ $selectedRole->name }}" required style="width: 100%; max-width: 400px; margin-top: 6px;">
                        </label>
                    </div>
                @endunless

                <!-- 4 Grouped Categories -->
                @php
                    $rolePerms = $selectedRole->default_permissions ?? [];
                @endphp

                @foreach($groupedCatalog as $categoryKey => $group)
                    <div class="permission-category-block" style="margin-bottom: 28px; border-top: 1px solid #E5E7EB; padding-top: 20px;">
                        <div class="category-block-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                            <div>
                                <h3 style="font-size: 15px; font-weight: 600; margin: 0 0 2px 0; color: #111827;">{{ $group['meta']['title'] }}</h3>
                                <p class="muted" style="font-size: 12px; margin: 0;">{{ $group['meta']['description'] }}</p>
                            </div>
                            <div class="category-toggle-links" style="font-size: 11px;">
                                <button type="button" class="text-link-action" onclick="toggleCategoryGroup('{{ $categoryKey }}', true)">Select All</button>
                                <span class="muted" style="margin: 0 4px;">·</span>
                                <button type="button" class="text-link-action" onclick="toggleCategoryGroup('{{ $categoryKey }}', false)">Deselect All</button>
                            </div>
                        </div>

                        <div class="permission-items-grid">
                            @foreach($group['permissions'] as $permKey => $permInfo)
                                @php
                                    $aliasKey = \App\Services\Access::ALIASES[$permKey] ?? null;
                                    $isChecked = in_array($permKey, $rolePerms, true) || ($aliasKey && in_array($aliasKey, $rolePerms, true));
                                    // Check legacy reverse alias
                                    if (!$isChecked) {
                                        foreach(\App\Services\Access::ALIASES as $legacy => $canonical) {
                                            if ($canonical === $permKey && in_array($legacy, $rolePerms, true)) {
                                                $isChecked = true;
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                <label class="permission-checkbox-card {{ $isChecked ? 'checked' : '' }}">
                                    <div class="checkbox-col">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permKey }}" 
                                               class="perm-box group-{{ $categoryKey }}" 
                                               @checked($isChecked)
                                               onchange="this.closest('.permission-checkbox-card').classList.toggle('checked', this.checked)">
                                    </div>
                                    <div class="perm-body-col">
                                        <div class="perm-name-row">
                                            <span class="perm-name">{{ $permInfo['name'] }}</span>
                                            <code class="perm-code-badge">{{ $permKey }}</code>
                                        </div>
                                        <p class="perm-desc">{{ $permInfo['description'] }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <!-- Single Primary Save Action -->
                <div class="editor-bottom-bar" style="position: sticky; bottom: 0; background: #FFFFFF; padding: 16px 0; border-top: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; z-index: 5;">
                    <div style="font-size: 13px; color: var(--muted);">
                        Role: <b>{{ $selectedRole->name }}</b>
                    </div>
                    <button type="submit" class="btn btn-primary" style="min-width: 140px;">Save Changes</button>
                </div>
            </form>
        @else
            <div class="empty-state" style="padding: 48px 24px;">
                <b>No role selected</b>
                <p class="muted">Select a role from the left sidebar to configure its permissions.</p>
            </div>
        @endif
    </main>
</div>

<!-- Effective Permissions Preview Section -->
<section class="card" style="margin-top: 32px;">
    <div class="card-head" style="margin-bottom: 20px;">
        <div>
            <h2 style="font-size: 16px; margin: 0 0 4px 0;">Effective Permissions Preview</h2>
            <p class="muted" style="margin: 0; font-size: 13px;">Verify what effective permissions a specific user has in a specific project based on role defaults, project-level overrides, and company-wide policies.</p>
        </div>
    </div>

    <form method="post" action="{{ route('role-directory.preview') }}" class="toolbar" style="margin-bottom: 20px; flex-wrap: wrap; gap: 12px; background: #F9FAFB; padding: 12px 16px; border-radius: 6px;">
        @csrf
        <input type="hidden" name="current_role_id" value="{{ $selectedRole?->id }}">
        
        <div style="display: flex; gap: 12px; flex: 1; flex-wrap: wrap;">
            <label style="font-size: 12px; font-weight: 500; min-width: 220px;">
                User
                <select name="user_id" required style="margin-top: 4px; width: 100%;">
                    <option value="">Select a user...</option>
                    @foreach($usersList as $u)
                        <option value="{{ $u->id }}" @selected($previewUserId == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </label>

            <label style="font-size: 12px; font-weight: 500; min-width: 240px;">
                Project
                <select name="project_id" required style="margin-top: 4px; width: 100%;">
                    <option value="">Select a project...</option>
                    @foreach($projectsList as $p)
                        <option value="{{ $p->id }}" @selected($previewProjectId == $p->id)>{{ $p->code }} · {{ $p->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button type="submit" class="btn btn-outline" style="height: 38px; align-self: flex-end;">Check Effective Permissions</button>
    </form>

    @if($effectivePreview)
        <div class="preview-results-box" style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden;">
            <div class="preview-header-summary" style="background: #F3F4F6; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div style="font-size: 13px;">
                    User: <b>{{ $effectivePreview['user']->name }}</b> &nbsp;·&nbsp;
                    Project: <b>{{ $effectivePreview['project']->code }} ({{ $effectivePreview['project']->name }})</b> &nbsp;·&nbsp;
                    Resolved Roles: <b>{{ implode(', ', $effectivePreview['roles']) }}</b>
                </div>
                <span class="status-pill status-{{ $effectivePreview['project']->status }}" style="font-size: 11px; padding: 2px 8px;">
                    Project Status: {{ ucfirst(str_replace('_', ' ', $effectivePreview['project']->status)) }}
                </span>
            </div>

            <div class="table-wrap">
                <table class="clean-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th style="width: 280px;">Permission</th>
                            <th style="width: 140px;">Category</th>
                            <th style="width: 120px;">Effective Access</th>
                            <th>Resolution Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($effectivePreview['permissions'] as $cat => $items)
                            @foreach($items as $item)
                                <tr>
                                    <td>
                                        <b>{{ $item['name'] }}</b>
                                        <div style="font-size: 11px; color: #6B7280; font-family: monospace;">{{ $item['key'] }}</div>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 500;">{{ $cat }}</span>
                                    </td>
                                    <td>
                                        @if($item['allowed'])
                                            <span class="status-pill pill-success" style="font-size: 11px; padding: 2px 8px;">Granted</span>
                                        @else
                                            <span class="status-pill pill-neutral" style="font-size: 11px; padding: 2px 8px; color: #9CA3AF;">Denied</span>
                                        @endif
                                    </td>
                                    <td style="color: {{ $item['allowed'] ? '#1F2937' : '#6B7280' }}; font-size: 12px;">
                                        {{ $item['source'] }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>

<!-- Scoped Styling for Clean SaaS RBAC -->
<style>
.rbac-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 24px;
    align-items: flex-start;
}
@media (max-width: 960px) {
    .rbac-grid {
        grid-template-columns: 1fr;
    }
}
.rbac-sidebar {
    padding: 16px;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: var(--radius);
}
.roles-search-input {
    width: 100%;
    padding: 7px 10px;
    font-size: 13px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    outline: none;
}
.roles-search-input:focus {
    border-color: #315F58;
}
.roles-nav-list {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.role-item-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    border-radius: 6px;
    text-decoration: none;
    color: #1F2937;
    transition: background 0.15s ease;
    border-left: 3px solid transparent;
}
.role-item-link:hover {
    background: #F9FAFB;
}
.role-item-link.active {
    background: #F3F4F6;
    border-left-color: #315F58;
    font-weight: 500;
}
.role-item-name {
    display: block;
    font-size: 13px;
}
.role-item-meta {
    display: block;
    font-size: 11px;
    color: #6B7280;
    margin-top: 2px;
}
.rbac-editor {
    padding: 24px;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: var(--radius);
}
.role-editor-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}
.permission-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
}
.permission-checkbox-card {
    display: flex;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    background: #FFFFFF;
    cursor: pointer;
    transition: all 0.15s ease;
}
.permission-checkbox-card:hover {
    border-color: #D1D5DB;
    background: #F9FAFB;
}
.permission-checkbox-card.checked {
    border-color: #A7F3D0;
    background: #F0FDF4;
}
.checkbox-col input[type="checkbox"] {
    margin-top: 2px;
    accent-color: #315F58;
    width: 15px;
    height: 15px;
    cursor: pointer;
}
.perm-body-col {
    flex: 1;
}
.perm-name-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 6px;
}
.perm-name {
    font-size: 12px;
    font-weight: 600;
    color: #1F2937;
}
.perm-code-badge {
    font-size: 10px;
    color: #6B7280;
    background: #F3F4F6;
    padding: 1px 4px;
    border-radius: 3px;
}
.perm-desc {
    font-size: 11px;
    color: #6B7280;
    margin: 3px 0 0 0;
    line-height: 1.35;
}
.text-link-action {
    background: none;
    border: none;
    padding: 0;
    color: #315F58;
    cursor: pointer;
    text-decoration: underline;
    font-size: 11px;
}
.text-link-action:hover {
    color: #1E3B36;
}
</style>

<script>
function toggleNewRoleForm() {
    const card = document.getElementById('new-role-card');
    if (card.style.display === 'none' || !card.style.display) {
        card.style.display = 'block';
        card.querySelector('input[name="name"]').focus();
    } else {
        card.style.display = 'none';
    }
}

function filterRolesLive(val) {
    const query = val.toLowerCase().trim();
    const items = document.querySelectorAll('#roles-nav-list .role-item-link');
    items.forEach(el => {
        const name = el.getAttribute('data-role-name') || '';
        const slug = el.getAttribute('data-role-slug') || '';
        if (name.includes(query) || slug.includes(query)) {
            el.style.display = 'flex';
        } else {
            el.style.display = 'none';
        }
    });
}

function toggleCategoryGroup(groupClass, check) {
    const boxes = document.querySelectorAll('.perm-box.group-' + groupClass);
    boxes.forEach(b => {
        b.checked = check;
        b.closest('.permission-checkbox-card').classList.toggle('checked', check);
    });
}
</script>

@endsection
