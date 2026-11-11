@extends('layouts.app')
@section('title', 'People')
@section('content')
<!-- Screen 9: Users Management (Clean, Low-Click, Subsidiary Column & Protected Administration) -->
<div class="page-head" style="margin-bottom: 20px;">
    <div>
        <h1 style="letter-spacing: -0.5px;">People</h1>
        <p class="muted" style="margin-top: 4px;">Manage your organization's directory, access roles, and project permissions.</p>
    </div>
    <div class="inline-actions">
        @if(auth()->user()->isPmo())<button class="btn btn-primary" data-show-dialog="user-dialog" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
            <span style="font-size: 16px; line-height: 1;">+</span> Add person
        </button>@endif
    </div>
</div>

<!-- Alert Box for AJAX Actions / Notifications -->
<div id="users-alert-box" style="display: none; margin-bottom: 20px;"></div>

<!-- Flash Temporary Password Alert (if generated) -->
@if(session('temp_password'))
    <div class="card" id="temp-password-banner" style="background: var(--primary-light); border-color: var(--primary-subtle); margin-bottom: 24px; position: relative; border-radius: 10px; padding: 18px 22px;">
        <button type="button" class="icon-btn" onclick="document.getElementById('temp-password-banner').style.display='none'" style="position: absolute; right: 12px; top: 12px;">×</button>
        <div class="card-head" style="margin-bottom: 8px;">
            <b>One-time temporary password generated</b>
            <span class="pill active">Active</span>
        </div>
        <p class="text-sm">User: <b>{{ session('temp_user') }}</b></p>
        <div style="display: flex; gap: 10px; align-items: center; margin: 12px 0;">
            <code id="temp-secret" style="background: #FFFFFF; padding: 8px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; font-weight: 600; font-family: monospace;">{{ session('temp_password') }}</code>
            <button class="btn btn-outline btn-sm" type="button" data-copy="temp-secret">Copy Password</button>
        </div>
        <small class="muted">Share securely with the user. The user will be required to change their password on first sign-in.</small>
    </div>
@endif

<!-- Metric Summary Cards -->
<div class="users-kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 22px;">
    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px; border: 1px solid var(--border); border-radius: 10px; background: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #EEF2F6; color: #334155; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
            👥
        </div>
        <div>
            <div style="font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted);">Total People</div>
            <div id="total-users-counter" style="font-size: 22px; font-weight: 700; color: var(--text); line-height: 1.2; margin-top: 2px;">{{ $totalUsersCount ?? $users->total() }}</div>
        </div>
    </div>
    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px; border: 1px solid var(--border); border-radius: 10px; background: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #DCFCE7; color: #166534; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
            ✓
        </div>
        <div>
            <div style="font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted);">Active Accounts</div>
            <div style="font-size: 22px; font-weight: 700; color: #166534; line-height: 1.2; margin-top: 2px;">{{ $activeUsersCount ?? $users->total() }}</div>
        </div>
    </div>
    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px; border: 1px solid var(--border); border-radius: 10px; background: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #E8F0EE; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
            🛡️
        </div>
        <div>
            <div style="font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted);">PMO Administrators</div>
            <div style="font-size: 22px; font-weight: 700; color: var(--primary); line-height: 1.2; margin-top: 2px;">{{ $pmoAdminCount }}</div>
        </div>
    </div>
    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px; border: 1px solid var(--border); border-radius: 10px; background: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #F1F5F9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
            🏢
        </div>
        <div>
            <div style="font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted);">Subsidiaries</div>
            <div style="font-size: 22px; font-weight: 700; color: var(--text); line-height: 1.2; margin-top: 2px;">{{ $subsidiaries->count() }}</div>
        </div>
    </div>
</div>

<!-- Users Filter & Table Card -->
<div class="card people-directory" style="padding: 0; overflow: hidden; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <form method="get" action="{{ route('users.index') }}" class="people-toolbar" id="people-filter-form" role="search" style="padding: 14px 20px; background: #FAFBFC; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <div class="people-search" style="flex: 1; min-width: 240px; position: relative;">
            <label class="workspace-sr-only" for="user-table-search">Search people by name or email</label>
            <input type="search" name="q" id="user-table-search" placeholder="Search people by name or email..." value="{{ $q }}" autocomplete="off" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid var(--border); padding: 8px 12px; font-size: 13.5px;">
            <span id="user-search-count" class="workspace-sr-only"></span>
        </div>
        <label class="people-company" style="margin: 0;">
            <span class="workspace-sr-only">Company</span>
            <select name="subsidiary_id" id="people-company-filter" style="height: 38px; border-radius: 8px; border: 1px solid var(--border); padding: 0 12px; font-size: 13px; background: #FFFFFF;">
                <option value="">All subsidiaries</option>
                @foreach($subsidiaries as $sub)
                    <option value="{{ $sub->id }}" @selected($subId == $sub->id)>{{ $sub->name }}</option>
                @endforeach
            </select>
        </label>
        <span class="people-result-count" role="status" style="font-size: 12.5px; font-weight: 550; color: var(--muted); padding: 4px 8px;">
            {{ $users->total() }} {{ $users->total() === 1 ? 'person' : 'people' }}
        </span>
        @if($q !== '' || $subId)
            <a class="text-action" href="{{ route('users.index') }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary);">Clear</a>
        @endif
    </form>

    <!-- Users Table (With Status column, Safe Delete & No Reset Button) -->
    <div class="table-wrap">
        <table id="users-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #FFFFFF; border-bottom: 1px solid var(--border); text-align: left;">
                    <th style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Person</th>
                    <th style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">System Role</th>
                    <th style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Subsidiary</th>
                    <th style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Account Status</th>
                    <th class="right" style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="users-tbody">
                @forelse($users as $user)
                    <tr id="user-row-{{ $user->id }}" class="user-row" data-name="{{ strtolower($user->name) }}" data-email="{{ strtolower($user->email) }}" data-sub-id="{{ $user->subsidiary_id ?? '' }}" style="border-bottom: 1px solid var(--border); transition: background-color 0.15s ease;">
                        <td style="padding: 12px 18px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="avatar avatar-sm" style="width: 36px; height: 36px; border-radius: 8px; font-size: 13px; font-weight: 650; background: #E8F0EE; color: var(--primary); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </span>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <a class="strong-link" href="{{ route('users.show', $user) }}" style="font-weight: 650; font-size: 13.5px; color: var(--text); text-decoration: none;">
                                            {{ $user->name }}
                                        </a>
                                        @if($user->socialAccounts && $user->socialAccounts->isNotEmpty())
                                            <span title="Synced with Microsoft Entra ID" style="display: inline-flex; align-items: center; font-size: 10px; background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; border-radius: 4px; padding: 1px 5px; font-weight: 600;">Azure SSO</span>
                                        @endif
                                    </div>
                                    <span class="people-email" style="display: block; font-size: 12px; color: var(--muted); margin-top: 1px;">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 12px 18px;">
                            @if($user->isPmo())
                                <span class="pill" style="display: inline-flex; align-items: center; gap: 5px; background: #E8F0EE; color: var(--primary); font-weight: 700; border: 1px solid #C8DDD2; padding: 3px 10px; border-radius: 12px; font-size: 11.5px;">
                                    🛡️ PMO Admin
                                </span>
                            @elseif($user->isAdminViewer())
                                <span class="pill" style="display: inline-flex; align-items: center; gap: 5px; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 600;">
                                    👁️ Admin Viewer
                                </span>
                            @else
                                <span class="pill" style="display: inline-flex; align-items: center; gap: 5px; background: #F8FAFC; color: #64748B; border: 1px solid #E2E8F0; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 600;">
                                    User
                                </span>
                            @endif
                        </td>
                        <td style="padding: 12px 18px;">
                            @if($user->subsidiary)
                                <span class="people-company-name" style="font-size: 13px; font-weight: 550; color: var(--text);">
                                    {{ $user->subsidiary->name }} <small style="font-size: 11px; color: var(--muted); font-weight: 500;">[{{ $user->subsidiary->code }}]</small>
                                </span>
                            @elseif($user->company_name)
                                <span class="people-company-name" style="font-size: 13px; font-weight: 550; color: var(--text);">
                                    {{ $user->company_name }}
                                </span>
                            @else
                                <span class="muted text-sm" style="color: var(--muted);">—</span>
                            @endif
                        </td>
                        <td style="padding: 12px 18px;">
                            @if($user->account_status === 'active')
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #166534;">
                                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 0 2px #DCFCE7;"></span>
                                    Active
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 550; color: #991B1B;">
                                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #EF4444; box-shadow: 0 0 0 2px #FEE2E2;"></span>
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="right" style="padding: 12px 18px; text-align: right;">
                            @if(auth()->user()->isPmo())
                                @php
                                    $isSelf = $user->id === auth()->id();
                                    $isPrimaryAdmin = $user->id === 1;
                                    $isLastPmo = $user->isPmo() && ($pmoAdminCount <= 1);
                                    $canDelete = !$isSelf && !$isPrimaryAdmin && !$isLastPmo;
                                @endphp
                                <div class="inline-actions people-row-actions" style="justify-content: flex-end; gap: 8px; align-items: center;">
                                    <button class="btn btn-outline btn-xs" type="button" title="Edit User" data-show-dialog="edit-user-{{ $user->id }}">Edit</button>

                                    @if($canDelete)
                                        <button class="btn btn-ghost btn-xs btn-delete-user" type="button" title="Delete User" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" style="color:var(--danger); font-weight:600;">Delete</button>
                                    @elseif($isLastPmo)
                                        <span class="pill" style="font-size: 10px; font-weight: 700; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; padding: 2px 7px;" title="Cannot delete: The system must keep at least one active PMO Administrator.">Last PMO Admin</span>
                                    @elseif($isSelf)
                                        <span class="pill" style="font-size: 10px; background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; padding: 2px 7px;" title="Currently signed in">You</span>
                                    @elseif($isPrimaryAdmin)
                                        <span class="pill" style="font-size: 10px; background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; padding: 2px 7px;" title="Primary Root Administrator">Primary Admin</span>
                                    @endif
                                </div>

                                <!-- Edit User Dialog -->
                                <dialog id="edit-user-{{ $user->id }}" class="modal">
                                    <div class="modal-head">
                                        <h2>Edit User: {{ $user->name }}</h2>
                                        <button type="button" class="icon-btn" data-close-dialog>×</button>
                                    </div>
                                    <form method="post" action="{{ route('users.update', $user) }}" class="stack mt-16 edit-user-form" data-user-id="{{ $user->id }}">
                                        @csrf
                                        @method('PATCH')

                                        <label>Full Name <span class="required">*</span>
                                            <input name="name" value="{{ $user->name }}" required maxlength="120">
                                        </label>

                                        <label>Email Address
                                            <input value="{{ $user->email }}" disabled style="background: var(--surface);">
                                        </label>

                                        <!-- Role Selection (User or PMO or Admin Viewer) -->
                                        <label>System Role <span class="required">*</span>
                                            <select name="system_role" {{ ($user->id === 1 || $user->id === auth()->id() || $isLastPmo) ? 'disabled' : '' }}>
                                                <option value="user" @selected($user->system_role === 'user')>User (Project Manager / Team Member)</option>
                                                <option value="pmo" @selected($user->system_role === 'pmo')>PMO Admin (System Administrator)</option>
                                                <option value="admin_viewer" @selected($user->system_role === 'admin_viewer')>Admin Viewer (Read only)</option>
                                            </select>
                                            @if($isLastPmo)
                                                <small style="color: #B45309; font-weight: 550; display: block; margin-top: 4px;">⚠️ Cannot demote the only PMO Administrator. Promote another user to PMO Administrator first.</small>
                                            @endif
                                        </label>

                                        <!-- Subsidiary Selection -->
                                        @if(count($subsidiaries))
                                            <label>Subsidiary / Company
                                                <select name="subsidiary_id">
                                                    <option value="">-- None / General Group --</option>
                                                    @foreach($subsidiaries as $s)
                                                        <option value="{{ $s->id }}" @selected($user->subsidiary_id == $s->id)>
                                                            {{ $s->name }} [{{ $s->code }}]
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        @endif

                                        <label>Account Status
                                            <select name="account_status" {{ ($user->id === 1 || $isLastPmo) ? 'disabled' : '' }}>
                                                <option value="active" @selected($user->account_status === 'active')>Enabled (Active)</option>
                                                <option value="inactive" @selected($user->account_status === 'inactive')>Disabled (Inactive)</option>
                                            </select>
                                            @if($isLastPmo)
                                                <small style="color: #B45309; font-weight: 550; display: block; margin-top: 4px;">⚠️ Cannot deactivate the only PMO Administrator.</small>
                                            @endif
                                        </label>

                                        <div class="form-actions mt-16">
                                            <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                                            <button class="btn btn-primary" type="submit">Save Changes</button>
                                        </div>
                                    </form>
                                </dialog>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr id="empty-users-row">
                        <td colspan="5" class="empty" style="text-align: center; padding: 36px 16px; color: var(--muted);">No users found. Click <b>+ Add person</b> to get started.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="pagination-wrap" style="padding: 12px 20px; border-top: 1px solid var(--border);">
            {{ $users->links() }}
        </div>
    @endif
</div>

<!-- =========================================================================
     Unified "+ Add User" Dialog (2 Tabs: ☁ Microsoft Azure & ✍ Manual Create)
     ========================================================================= -->
@if(auth()->user()->isPmo())
<dialog id="user-dialog" class="modal" style="max-width: 620px;">
    <div class="modal-head">
        <h2>Add person</h2>
        <button type="button" class="icon-btn" data-close-dialog>×</button>
    </div>

    <!-- Clean Tab Switcher -->
    <div style="display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 18px; padding-bottom: 2px;">
        <button type="button" id="tab-btn-azure" class="tab-link active" onclick="switchAddTab('azure')" style="padding: 8px 16px; font-weight: 650; font-size: 13px; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid var(--primary); color: var(--primary);">
            From Microsoft Azure
        </button>
        <button type="button" id="tab-btn-manual" class="tab-link" onclick="switchAddTab('manual')" style="padding: 8px 16px; font-weight: 600; font-size: 13px; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid transparent; color: var(--muted);">
            Manual Create
        </button>
    </div>

    <!-- Tab 1: Microsoft Azure Live Search & 1-Click Add -->
    <div id="tab-pane-azure">
        <p class="muted text-sm" style="margin-bottom: 12px;">Search your organization's Microsoft Entra ID and add users with a single click.</p>

        <div style="position: relative; display: flex; align-items: center; margin-bottom: 14px;">
            <input
                type="text"
                id="modal-azure-search"
                placeholder="Type name or work email (e.g. Aaron, abishan@gsconsumer.lk)..."
                autocomplete="off"
                style="width: 100%; padding-left: 14px; height: 38px; font-size: 13.5px; border-radius: var(--radius); border: 1px solid var(--border);"
            >
            <span id="modal-azure-spinner" style="position: absolute; right: 12px; font-size: 11px; color: var(--muted); display: none;">Searching...</span>
        </div>

        <!-- Azure Results Container inside Modal -->
        <div id="modal-azure-results" style="max-height: 280px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); padding: 8px;">
            <div id="modal-azure-empty" class="muted text-sm" style="text-align: center; padding: 24px 12px;">
                Type a name or email above to search Microsoft Entra ID.
            </div>
            <div id="modal-azure-list" style="display: none; display: flex; flex-direction: column; gap: 8px;"></div>
        </div>

        <div class="form-actions mt-16" style="justify-content: space-between; align-items: center;">
            <a href="{{ route('users.azure') }}" class="muted text-sm" style="text-decoration: underline;">Open dedicated Azure Search page →</a>
            <button type="button" class="btn btn-ghost" data-close-dialog>Close</button>
        </div>
    </div>

    <!-- Tab 2: Manual User Creation Form with Role Selection -->
    <div id="tab-pane-manual" style="display: none;">
        <p class="muted text-sm" style="margin-bottom: 12px;">Create a user manually with a temporary password and assigned role.</p>
        <div id="manual-user-error" class="alert danger" style="display: none; margin-bottom: 12px;"></div>

        <form id="manual-user-form" method="post" action="{{ route('users.store') }}" class="stack">
            @csrf

            <label>Full Name <span class="required">*</span>
                <input name="name" id="manual_user_name" required maxlength="120" placeholder="e.g. Nimal Perera">
            </label>

            <label>Work Email <span class="required">*</span>
                <input name="email" id="manual_user_email" type="email" required placeholder="nimal@company.com">
            </label>

            <!-- Role Selection -->
            <label>System Role <span class="required">*</span>
                <select name="system_role" id="manual_user_role">
                    <option value="user" selected>User (Project Manager / Team Member)</option>
                    <option value="pmo">PMO Admin (Full System Administrator)</option>
                    <option value="admin_viewer">Admin Viewer (Read only)</option>
                </select>
                <small class="muted block mt-4">PMO Admin has full configuration access. Standard Users can manage projects and tasks assigned to them.</small>
            </label>

            <!-- Subsidiary Selection -->
            @if(count($subsidiaries))
                <label>Subsidiary Entity (Optional)
                    <select name="subsidiary_id" id="manual_user_subsidiary">
                        <option value="">-- None / General Group --</option>
                        @foreach($subsidiaries as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} [{{ $s->code }}]</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="form-actions mt-16">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-manual-user">Create User</button>
            </div>
        </form>
    </div>
</dialog>

@push('scripts')
<script>
// Switch tabs inside Unified Add User Dialog
function switchAddTab(tab) {
    const paneAzure = document.getElementById('tab-pane-azure');
    const paneManual = document.getElementById('tab-pane-manual');
    const btnAzure = document.getElementById('tab-btn-azure');
    const btnManual = document.getElementById('tab-btn-manual');

    if (tab === 'azure') {
        paneAzure.style.display = 'block';
        paneManual.style.display = 'none';
        btnAzure.style.borderBottomColor = 'var(--primary)';
        btnAzure.style.color = 'var(--primary)';
        btnManual.style.borderBottomColor = 'transparent';
        btnManual.style.color = 'var(--muted)';
        const input = document.getElementById('modal-azure-search');
        if (input) setTimeout(() => input.focus(), 50);
    } else {
        paneAzure.style.display = 'none';
        paneManual.style.display = 'block';
        btnManual.style.borderBottomColor = 'var(--primary)';
        btnManual.style.color = 'var(--primary)';
        btnAzure.style.borderBottomColor = 'transparent';
        btnAzure.style.color = 'var(--muted)';
        const input = document.getElementById('manual_user_name');
        if (input) setTimeout(() => input.focus(), 50);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const alertBox = document.getElementById('users-alert-box');

    function showAlert(msg, type = 'success') {
        if (!alertBox) return;
        alertBox.className = 'alert ' + type;
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
        setTimeout(() => { alertBox.style.display = 'none'; }, 5000);
    }

    const filterForm = document.getElementById('people-filter-form');
    const tableSearch = document.getElementById('user-table-search');
    let searchTimer;
    tableSearch?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { sessionStorage.setItem('people-search-focus','1'); filterForm.requestSubmit(); }, 600);
    });
    document.getElementById('people-company-filter')?.addEventListener('change', () => { clearTimeout(searchTimer); filterForm.requestSubmit(); });
    if (sessionStorage.getItem('people-search-focus') === '1') {
        sessionStorage.removeItem('people-search-focus'); tableSearch.focus();
        try { tableSearch.setSelectionRange(tableSearch.value.length, tableSearch.value.length); } catch (_) {}
    }

    // ---------------------------------------------------------
    // 1-Click User Delete (Permanent Delete with safe cleanup)
    // ---------------------------------------------------------
    document.querySelectorAll('.btn-delete-user').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName || 'User';

            if (!await AppDialog.confirm(`Are you sure you want to permanently delete user "${userName}"?\n\nThis will remove their project access, task assignments, and memberships.`)) {
                return;
            }

            this.disabled = true;
            const row = document.getElementById('user-row-' + userId);

            try {
                const res = await fetch(`/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Could not delete user.');

                if (row) {
                    row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        row.remove();
                        const counter = document.getElementById('total-users-counter');
                        if (counter) {
                            const cur = parseInt(counter.textContent) || 1;
                            counter.textContent = Math.max(0, cur - 1);
                        }
                    }, 300);
                }

                showAlert(`✓ User "${userName}" has been deleted.`, 'success');
            } catch (err) {
                this.disabled = false;
                showAlert(err.message, 'danger');
            }
        });
    });

    // ---------------------------------------------------------
    // Modal Tab 1: Live Azure Search inside "+ Add User"
    // ---------------------------------------------------------
    const modalAzureSearch = document.getElementById('modal-azure-search');
    const modalAzureSpinner = document.getElementById('modal-azure-spinner');
    const modalAzureEmpty = document.getElementById('modal-azure-empty');
    const modalAzureList = document.getElementById('modal-azure-list');
    let modalDebounce = null;

    if (modalAzureSearch) {
        modalAzureSearch.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(modalDebounce);

            if (query.length < 2) {
                if (modalAzureSpinner) modalAzureSpinner.style.display = 'none';
                if (modalAzureList) modalAzureList.style.display = 'none';
                if (modalAzureEmpty) {
                    modalAzureEmpty.style.display = 'block';
                    modalAzureEmpty.textContent = 'Type a name or email above to search Microsoft Entra ID.';
                }
                return;
            }

            if (modalAzureSpinner) modalAzureSpinner.style.display = 'inline';

            modalDebounce = setTimeout(async function() {
                try {
                    const res = await fetch(`{{ route('users.azure') }}?q=${encodeURIComponent(query)}&ajax=1`, {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (modalAzureSpinner) modalAzureSpinner.style.display = 'none';
                    if (!res.ok) return;

                    const data = await res.json();
                    renderModalAzureResults(data.results, query);
                } catch (e) {
                    if (modalAzureSpinner) modalAzureSpinner.style.display = 'none';
                }
            }, 300);
        });
    }

    function renderModalAzureResults(items, query) {
        if (!items || items.length === 0) {
            modalAzureList.style.display = 'none';
            modalAzureEmpty.style.display = 'block';
            modalAzureEmpty.textContent = `No users found matching "${query}".`;
            return;
        }

        modalAzureEmpty.style.display = 'none';
        modalAzureList.style.display = 'flex';

        let html = '';
        items.forEach(u => {
            const initial = (u.displayName ? u.displayName.charAt(0) : 'U').toUpperCase();
            const actionBtn = u.isAdded
                ? `<span class="pill active" style="font-size: 11px;">✓ In System</span>`
                : `<button type="button" class="btn btn-primary btn-sm btn-modal-add-azure" data-azure-id="${u.id}" data-user-name="${escapeHtml(u.displayName)}">+ Add</button>`;

            html += `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #FFFFFF; border: 1px solid var(--border); border-radius: var(--radius); gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        <span class="avatar avatar-sm" style="flex-shrink: 0;">${initial}</span>
                        <div style="min-width: 0;">
                            <b style="font-size: 13px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.displayName)}</b>
                            <small class="muted" style="display: block; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.email)} ${u.company ? '· ' + escapeHtml(u.company) : ''}</small>
                        </div>
                    </div>
                    <div id="modal-azure-action-${u.id}">
                        ${actionBtn}
                    </div>
                </div>
            `;
        });

        modalAzureList.innerHTML = html;

        // Bind Add click
        modalAzureList.querySelectorAll('.btn-modal-add-azure').forEach(btn => {
            btn.addEventListener('click', async function() {
                const azureId = this.dataset.azureId;
                const userName = this.dataset.userName || 'User';
                const cell = document.getElementById('modal-azure-action-' + azureId);

                this.disabled = true;
                this.textContent = 'Adding...';

                try {
                    const res = await fetch("{{ route('users.azure.add') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ azure_id: azureId })
                    });

                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || 'Failed to add user.');

                    if (cell) {
                        cell.innerHTML = '<span class="pill active" style="font-size: 11px;">✓ Added</span>';
                    }

                    showAlert(`✓ Successfully added ${userName} from Microsoft Azure!`, 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } catch (err) {
                    this.disabled = false;
                    this.textContent = '+ Add';
                    alert(err.message);
                }
            });
        });
    }

    // ---------------------------------------------------------
    // Modal Tab 2: Manual User Creation with Role
    // ---------------------------------------------------------
    const btnSaveManual = document.getElementById('btn-save-manual-user');
    if (btnSaveManual) {
        btnSaveManual.addEventListener('click', async function() {
            const nameInput = document.getElementById('manual_user_name');
            const emailInput = document.getElementById('manual_user_email');
            const roleSelect = document.getElementById('manual_user_role');
            const subSelect = document.getElementById('manual_user_subsidiary');
            const errorBox = document.getElementById('manual-user-error');
            const dialog = document.getElementById('user-dialog');

            if (!nameInput.value.trim() || !emailInput.value.trim()) {
                errorBox.style.display = 'block';
                errorBox.textContent = 'Please enter both full name and a valid work email.';
                return;
            }

            errorBox.style.display = 'none';
            btnSaveManual.disabled = true;
            btnSaveManual.textContent = 'Creating...';

            try {
                const res = await fetch("{{ route('users.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        name: nameInput.value.trim(),
                        email: emailInput.value.trim(),
                        system_role: roleSelect ? roleSelect.value : 'user',
                        subsidiary_id: subSelect ? subSelect.value : null
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not create user.'));
                }

                if (dialog && typeof dialog.close === 'function') dialog.close();

                alert(`User created successfully!\n\nName: ${data.user.name}\nEmail: ${data.user.email}\nTemporary Password: ${data.temp_password}\n\nPlease copy this password securely for the user.`);
                window.location.reload();
            } catch (err) {
                errorBox.style.display = 'block';
                errorBox.textContent = err.message;
            } finally {
                btnSaveManual.disabled = false;
                btnSaveManual.textContent = 'Create User';
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }
});
</script>
@endpush
@endif
@endsection
