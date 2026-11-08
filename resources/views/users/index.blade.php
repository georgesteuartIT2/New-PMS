@extends('layouts.app')
@section('title', 'People')
@section('content')
<!-- Screen 9: Users Management (Clean, Low-Click, Subsidiary Column & Delete) -->
<div class="page-head">
    <div>
        <h1>People</h1>
        <p class="muted">Manage your team and system access in one place.</p>
    </div>
    <div class="inline-actions">

        <button class="btn btn-primary" data-show-dialog="user-dialog" style="display: inline-flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px; line-height: 1;">+</span> Add person
        </button>
    </div>
</div>

<!-- Alert Box for AJAX Actions / Notifications -->
<div id="users-alert-box" style="display: none; margin-bottom: 20px;"></div>

<!-- Flash Temporary Password Alert (if generated) -->
@if(session('temp_password'))
    <div class="card" id="temp-password-banner" style="background: var(--primary-light); border-color: var(--primary-subtle); margin-bottom: 24px; position: relative;">
        <button type="button" class="icon-btn" onclick="document.getElementById('temp-password-banner').style.display='none'" style="position: absolute; right: 12px; top: 12px;">×</button>
        <div class="card-head" style="margin-bottom: 8px;">
            <b>One-time temporary password generated</b>
            <span class="pill active">Active</span>
        </div>
        <p class="text-sm">User: <b>{{ session('temp_user') }}</b></p>
        <div style="display: flex; gap: 10px; align-items: center; margin: 12px 0;">
            <code id="temp-secret" style="background: #FFFFFF; padding: 8px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; font-weight: 600;">{{ session('temp_password') }}</code>
            <button class="btn btn-outline btn-sm" type="button" data-copy="temp-secret">Copy Password</button>
        </div>
        <small class="muted">Share securely with the user. The user will be required to change their password on first sign-in.</small>
    </div>
@endif

<!-- Users Filter & Table Card -->
<div class="card people-directory" style="padding: 0; overflow: hidden;">
    <form method="get" action="{{ route('users.index') }}" class="people-toolbar" id="people-filter-form" role="search">
        <div class="people-search"><label class="workspace-sr-only" for="user-table-search">Search people by name or email</label><input type="search" name="q" id="user-table-search" placeholder="Search name or email..." value="{{ $q }}" autocomplete="off"><span id="user-search-count" class="workspace-sr-only"></span></div>
        <label class="people-company"><span class="workspace-sr-only">Company</span><select name="subsidiary_id" id="people-company-filter"><option value="">All companies</option>@foreach($subsidiaries as $sub)<option value="{{ $sub->id }}" @selected($subId == $sub->id)>{{ $sub->name }}</option>@endforeach</select></label>
        <span class="people-result-count" role="status">{{ $users->total() }} {{ $users->total() === 1 ? 'person' : 'people' }}</span>
        @if($q !== '' || $subId)<a class="text-action" href="{{ route('users.index') }}">Clear</a>@endif
    </form>

    <!-- Users Table (With SUBSIDIARY column & DELETE action) -->
    <div class="table-wrap">
        <table id="users-table">
            <thead>
                <tr>
                    <th>Person</th>
                    <th>System Role</th>
                    <th>Subsidiary</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody id="users-tbody">
                @forelse($users as $user)
                    <tr id="user-row-{{ $user->id }}" class="user-row" data-name="{{ strtolower($user->name) }}" data-email="{{ strtolower($user->email) }}" data-sub-id="{{ $user->subsidiary_id ?? '' }}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="avatar avatar-sm">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                <div>
                                    <a class="strong-link" href="{{ route('users.show', $user) }}" style="font-weight: 650; font-size: 13.5px;">
                                        {{ $user->name }}
                                    </a>
                                    <span class="people-email">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($user->isPmo())
                                <span class="pill" style="background: #E8F0EE; color: var(--primary); font-weight: 700;">PMO Admin</span>
                            @else
                                <span class="pill" style="background: var(--surface); color: var(--text); border: 1px solid var(--border);">User</span>
                            @endif
                        </td>
                        <td>
                            @if($user->subsidiary)
                                <span class="people-company-name">
                                    {{ $user->subsidiary->name }} <small style="font-size: 10.5px; opacity: 0.85;">[{{ $user->subsidiary->code }}]</small>
                                </span>
                            @elseif($user->company_name)
                                <span class="people-company-name">
                                    {{ $user->company_name }}
                                </span>
                            @else
                                <span class="muted text-sm">—</span>
                            @endif
                        </td>
                        <td class="right">
                            <div class="inline-actions people-row-actions" style="justify-content: flex-end; gap: 8px;">
                                <button class="btn btn-outline btn-xs" type="button" title="Edit User" data-show-dialog="edit-user-{{ $user->id }}">Edit</button>

                                @if($user->password && !$user->isPmo())
                                    <button class="btn btn-outline btn-xs btn-reset-password" type="button" title="Reset Password" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" style="color:var(--warning);">Reset password</button>
                                @endif

                                @if($user->id !== 1 && $user->id !== auth()->id())
                                    <button class="btn btn-ghost btn-xs btn-delete-user" type="button" title="Delete User" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" style="color:var(--danger);">Delete</button>
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

                                    <!-- Role Selection (User or PMO) -->
                                    <label>System Role <span class="required">*</span>
                                        <select name="system_role" {{ ($user->id === 1 || $user->id === auth()->id()) ? 'disabled' : '' }}>
                                            <option value="user" @selected($user->system_role === 'user')>User (Project Manager / Team Member)</option>
                                            <option value="pmo" @selected($user->system_role === 'pmo')>PMO Admin (System Administrator)</option>
                                        </select>
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
                                        <select name="account_status" {{ ($user->id === 1) ? 'disabled' : '' }}>
                                            <option value="active" @selected($user->account_status === 'active')>Enabled (Active)</option>
                                            <option value="inactive" @selected($user->account_status === 'inactive')>Disabled (Inactive)</option>
                                        </select>
                                    </label>

                                    <div class="form-actions mt-16">
                                        <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                                        <button class="btn btn-primary" type="submit">Save Changes</button>
                                    </div>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr id="empty-users-row">
                        <td colspan="4" class="empty">No users found. Click <b>+ Add User</b> to get started.</td>
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
    // 2. 1-Click User Delete (Permanent Delete with safe cleanup)
    // ---------------------------------------------------------
    document.querySelectorAll('.btn-delete-user').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName || 'User';

            if (!confirm(`Are you sure you want to permanently delete user "${userName}"?\n\nThis will remove their project access, task assignments, and memberships.`)) {
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
    // 3. 1-Click Temporary Password Reset
    // ---------------------------------------------------------
    document.querySelectorAll('.btn-reset-password').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName || 'User';

            if (!confirm(`Generate a new temporary password for ${userName}?`)) return;

            btn.disabled = true;

            try {
                const res = await fetch(`/users/${userId}/temporary-password`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Password reset failed.');

                alert(`Temporary password generated for ${userName}:\n\n${data.temp_password}\n\nCopy this password now. The user will be required to change it on login.`);
            } catch (err) {
                showAlert(err.message, 'danger');
            } finally {
                btn.disabled = false;
            }
        });
    });

    // ---------------------------------------------------------
    // 4. Modal Tab 1: Live Azure Search inside "+ Add User"
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
                    modalAzureEmpty.textContent = '🔍 Type a name or email above to search Microsoft Entra ID.';
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
    // 5. Modal Tab 2: Manual User Creation with Role
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
@endsection
