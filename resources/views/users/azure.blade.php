@extends('layouts.app')
@section('title', 'Add from Azure')
@section('content')
<!-- Add from Microsoft Azure Entra -->
<div class="page-head">
    <div>
        <div class="breadcrumb-trail">
            <a href="{{ route('users.index') }}">Users</a>
            <span class="crumb-sep">›</span>
            <span>Add from Microsoft Azure</span>
        </div>
        <h1>Add from Microsoft Azure</h1>
        <p class="muted">Search by name or email to add team members from Microsoft Entra.</p>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-outline">← Back to Users</a>
</div>

<!-- Search Card (Minimal & Centered Focus) -->
<div class="card" style="margin-bottom: 20px;">
    <form method="get" action="{{ route('users.azure') }}" id="azure-search-form" class="toolbar" style="gap: 10px;">
        <div style="flex: 1; position: relative; display: flex; align-items: center;">
            <input 
                type="text" 
                name="q" 
                id="azure-search-input" 
                value="{{ $q }}" 
                placeholder="Type name or work email (e.g. Nirmalan, aaron@gstravels.lk)..." 
                aria-label="Search Azure Users" 
                autofocus
                autocomplete="off"
                style="width: 100%; padding-left: 14px; height: 42px; font-size: 14px; border-radius: var(--radius); border: 1px solid var(--border);"
            >
            <span id="search-spinner" style="position: absolute; right: 14px; display: none; font-size: 12px; color: var(--muted);">Searching...</span>
        </div>
        <button class="btn btn-primary" type="submit" id="btn-search" style="height: 42px; padding: 0 20px;">Search</button>
        @if($q)
            <a href="{{ route('users.azure') }}" class="btn btn-ghost" style="height: 42px; line-height: 40px;">Clear</a>
        @endif
    </form>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 12px; color: var(--muted); padding: 0 4px;">
        <span>Connected to Microsoft Azure Entra ID</span>
        <span style="display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--success);"></span>
            Active Directory
        </span>
    </div>
</div>

<!-- AJAX / Feedback Message Box -->
<div id="azure-alert-box" style="display: none; margin-bottom: 16px;"></div>

@if($error)
    <div class="alert danger mt-16">{{ $error }}</div>
@endif

<!-- Results Container -->
<div class="card" id="results-card" style="padding: 0; overflow: hidden;">
    <!-- State 1: When results are loaded -->
    <div id="results-table-wrap" class="table-wrap" style="{{ count($results) ? '' : 'display: none;' }}">
        <table>
            <thead>
                <tr>
                    <th>User in Microsoft Entra</th>
                    <th>Work Email</th>
                    <th>Company / Subsidiary</th>
                    <th>Status</th>
                    <th class="right">Action</th>
                </tr>
            </thead>
            <tbody id="results-tbody">
                @foreach($results as $result)
                    @php
                        $email = strtolower(trim($result['mail'] ?: ($result['userPrincipalName'] ?? '')));
                        $isAdded = in_array($result['id'], $existing);
                        $hasLocalMatch = $emailMatches->has($email);
                        $company = trim($result['companyName'] ?? ($result['department'] ?? ''));
                    @endphp
                    <tr id="row-{{ $result['id'] }}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="avatar avatar-sm">{{ strtoupper(substr($result['displayName'] ?? 'U', 0, 1)) }}</span>
                                <div>
                                    <b style="font-size: 13.5px; display: block;">{{ $result['displayName'] ?? 'Unknown' }}</b>
                                    @if(!empty($result['jobTitle']))
                                        <small class="muted">{{ $result['jobTitle'] }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 13px;">{{ $email ?: 'No email configured' }}</span>
                        </td>
                        <td>
                            @if(!empty($company))
                                <span class="pill todo">{{ $company }}</span>
                            @else
                                <span class="muted text-sm">—</span>
                            @endif
                        </td>
                        <td id="status-cell-{{ $result['id'] }}">
                            @if($isAdded)
                                <span class="pill active">Added</span>
                            @elseif($hasLocalMatch)
                                <span class="pill in_progress">Local Match</span>
                            @else
                                <span class="pill todo">Not in System</span>
                            @endif
                        </td>
                        <td class="right" id="action-cell-{{ $result['id'] }}">
                            @if($isAdded)
                                <span class="pill active">In System</span>
                            @elseif($hasLocalMatch)
                                <form method="post" action="{{ route('users.azure.add') }}" class="add-user-form" data-azure-id="{{ $result['id'] }}" data-user-name="{{ $result['displayName'] ?? 'User' }}" style="display: inline-block;">
                                    @csrf
                                    <input type="hidden" name="azure_id" value="{{ $result['id'] }}">
                                    <button class="btn btn-outline btn-sm btn-add-user" type="submit">Link Account</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('users.azure.add') }}" class="add-user-form" data-azure-id="{{ $result['id'] }}" data-user-name="{{ $result['displayName'] ?? 'User' }}" style="display: inline-block;">
                                    @csrf
                                    <input type="hidden" name="azure_id" value="{{ $result['id'] }}">
                                    <button class="btn btn-primary btn-sm btn-add-user" type="submit">+ Add User</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- State 2: When searched but 0 results found -->
    <div id="no-results-box" class="empty" style="{{ ($q && count($results) === 0) ? '' : 'display: none;' }}; padding: 48px 24px;">
        <b style="font-size: 15px;">No directory users found matching "<span id="no-results-query">{{ $q }}</span>"</b>
        <p class="muted" style="margin-top: 4px; font-size: 13px;">Check the spelling or try searching by work email.</p>
    </div>

    <!-- State 3: Initial empty prompt (before searching) -->
    <div id="initial-prompt-box" class="empty" style="{{ (!$q && count($results) === 0) ? '' : 'display: none;' }}; padding: 56px 24px;">
        <div class="empty-icon" style="font-size: 34px; margin-bottom: 10px;">🔍</div>
        <b style="font-size: 16px;">Search by Name or Email</b>
        <p class="muted" style="max-width: 440px; margin: 8px auto 0; font-size: 13.5px; line-height: 1.5;">
            Type a team member's name or work email above to search Microsoft Azure Entra. Add them to GS ProjectFlow with a single click.
        </p>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('azure-search-input');
    const spinner = document.getElementById('search-spinner');
    const resultsTableWrap = document.getElementById('results-table-wrap');
    const resultsTbody = document.getElementById('results-tbody');
    const noResultsBox = document.getElementById('no-results-box');
    const noResultsQuery = document.getElementById('no-results-query');
    const initialPromptBox = document.getElementById('initial-prompt-box');
    const alertBox = document.getElementById('azure-alert-box');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    let debounceTimer = null;

    // Show temporary alert banner
    function showAlert(message, type = 'success') {
        alertBox.className = 'alert ' + type;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        setTimeout(() => {
            alertBox.style.display = 'none';
        }, 5000);
    }

    // Attach click/submit listener to Add User forms
    function bindAddUserButtons() {
        document.querySelectorAll('.add-user-form').forEach(form => {
            if (form.dataset.bound) return;
            form.dataset.bound = 'true';

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const azureId = this.dataset.azureId;
                const userName = this.dataset.userName || 'User';
                const btn = this.querySelector('.btn-add-user');
                const actionCell = document.getElementById('action-cell-' + azureId);
                const statusCell = document.getElementById('status-cell-' + azureId);

                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Adding...';
                }

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
                    if (!res.ok) {
                        throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not add user.'));
                    }

                    // Update UI in-place with 0 clicks / zero refresh
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="pill active">Added</span>';
                    }
                    if (actionCell) {
                        actionCell.innerHTML = '<span class="pill active">In System</span>';
                    }

                    showAlert(data.message || 'User added successfully!', 'success');
                } catch (err) {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = '+ Add User';
                    }
                    showAlert(err.message, 'danger');
                }
            });
        });
    }

    // Render search results from AJAX
    function renderResults(items, query) {
        if (!items || items.length === 0) {
            resultsTableWrap.style.display = 'none';
            initialPromptBox.style.display = 'none';
            noResultsBox.style.display = 'block';
            if (noResultsQuery) noResultsQuery.textContent = query;
            return;
        }

        noResultsBox.style.display = 'none';
        initialPromptBox.style.display = 'none';
        resultsTableWrap.style.display = 'block';

        let html = '';
        items.forEach(u => {
            const initial = (u.displayName ? u.displayName.charAt(0) : 'U').toUpperCase();
            const email = u.email || '';
            const company = u.company || '';

            let statusPill = '<span class="pill todo">Not in System</span>';
            if (u.isAdded) {
                statusPill = '<span class="pill active">Added</span>';
            } else if (u.hasLocalMatch) {
                statusPill = '<span class="pill in_progress">Local Match</span>';
            }

            let actionHtml = '';
            if (u.isAdded) {
                actionHtml = '<span class="pill active">In System</span>';
            } else if (u.hasLocalMatch) {
                actionHtml = `
                    <form method="post" action="{{ route('users.azure.add') }}" class="add-user-form" data-azure-id="${u.id}" data-user-name="${escapeHtml(u.displayName)}" style="display: inline-block;">
                        <input type="hidden" name="azure_id" value="${u.id}">
                        <button class="btn btn-outline btn-sm btn-add-user" type="submit">Link Account</button>
                    </form>
                `;
            } else {
                actionHtml = `
                    <form method="post" action="{{ route('users.azure.add') }}" class="add-user-form" data-azure-id="${u.id}" data-user-name="${escapeHtml(u.displayName)}" style="display: inline-block;">
                        <input type="hidden" name="azure_id" value="${u.id}">
                        <button class="btn btn-primary btn-sm btn-add-user" type="submit">+ Add User</button>
                    </form>
                `;
            }

            html += `
                <tr id="row-${u.id}">
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="avatar avatar-sm">${initial}</span>
                            <div>
                                <b style="font-size: 13.5px; display: block;">${escapeHtml(u.displayName)}</b>
                                ${u.jobTitle ? `<small class="muted">${escapeHtml(u.jobTitle)}</small>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="font-size: 13px;">${escapeHtml(email) || 'No email configured'}</span>
                    </td>
                    <td>
                        ${company ? `<span class="pill todo">${escapeHtml(company)}</span>` : '<span class="muted text-sm">—</span>'}
                    </td>
                    <td id="status-cell-${u.id}">${statusPill}</td>
                    <td class="right" id="action-cell-${u.id}">${actionHtml}</td>
                </tr>
            `;
        });

        resultsTbody.innerHTML = html;
        bindAddUserButtons();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    // Live search as user types (debounced 350ms)
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                if (spinner) spinner.style.display = 'none';
                resultsTableWrap.style.display = 'none';
                noResultsBox.style.display = 'none';
                initialPromptBox.style.display = 'block';
                return;
            }

            if (spinner) spinner.style.display = 'inline';

            debounceTimer = setTimeout(async function() {
                try {
                    const res = await fetch(`{{ route('users.azure') }}?q=${encodeURIComponent(query)}&ajax=1`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (spinner) spinner.style.display = 'none';

                    if (!res.ok) {
                        return;
                    }

                    const data = await res.json();
                    renderResults(data.results, query);
                } catch (e) {
                    if (spinner) spinner.style.display = 'none';
                }
            }, 350);
        });
    }

    // Initial binding for any SSR rendered rows
    bindAddUserButtons();
});
</script>
@endpush
@endsection
