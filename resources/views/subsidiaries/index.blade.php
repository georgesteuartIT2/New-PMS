@extends('layouts.app')
@section('title', 'Subsidiaries')
@section('content')
<div class="page-head">
    <div>
        <h1>Subsidiaries</h1>
        <p class="muted">Manage group companies, subsidiaries, and their distinct project numbering prefixes.</p>
    </div>
    <div class="inline-actions">
        <a href="{{ route('subsidiaries.index', ['check_azure' => 1]) }}" class="btn btn-outline">
            Check Azure Entra Entities
        </a>
        <button class="btn btn-primary" data-show-dialog="add-subsidiary-dialog">+ Add Subsidiary</button>
    </div>
</div>

@if(isset($syncError))
    <div class="alert danger">
        {{ $syncError }}
    </div>
@endif

@if(request()->has('check_azure') && count($azureCompanies))
    <div class="card" style="border-left: 4px solid var(--primary); background: #F8FAFB; margin-bottom: 24px;">
        <div class="card-head" style="margin-bottom: 8px;">
            <b>Entities Discovered in Microsoft Azure AD / Entra ID</b>
            <span class="pill active">{{ count($azureCompanies) }} Found</span>
        </div>
        <p class="muted text-sm">The following companies/departments were found on user profiles in your Azure tenant. You can import them as system subsidiaries with one click.</p>
        
        <div class="table-wrap mt-16">
            <table>
                <thead>
                    <tr>
                        <th>Company / Department in Azure</th>
                        <th>Suggested Code</th>
                        <th>Type</th>
                        <th class="right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($azureCompanies as $ac)
                        @php
                            $alreadyExists = $subsidiaries->first(fn($s) => strtolower($s->name) === strtolower($ac['name']) || strtolower($s->azure_company_name) === strtolower($ac['name']));
                        @endphp
                        <tr>
                            <td><b>{{ $ac['name'] }}</b></td>
                            <td><span class="pill todo">[{{ $ac['code'] }}]</span></td>
                            <td><small class="muted">{{ $ac['source'] }}</small></td>
                            <td class="right">
                                @if($alreadyExists)
                                    <span class="pill active">Linked to {{ $alreadyExists->code }}</span>
                                @else
                                    <form method="post" action="{{ route('subsidiaries.import.azure') }}" style="display: inline-block;">
                                        @csrf
                                        <input type="hidden" name="name" value="{{ $ac['name'] }}">
                                        <input type="hidden" name="code" value="{{ $ac['code'] }}">
                                        <input type="hidden" name="azure_company_name" value="{{ $ac['name'] }}">
                                        <button type="submit" class="btn btn-primary btn-sm">+ Import</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Subsidiary Name</th>
                    <th>Code Prefix</th>
                    <th>Generated Project ID Pattern</th>
                    <th>Total Projects</th>
                    <th>Azure Mapping</th>
                    <th>Status</th>
                    <th class="right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subsidiaries as $sub)
                    <tr>
                        <td>
                            <b>{{ $sub->name }}</b>
                            @if($sub->description)
                                <small class="block muted">{{ $sub->description }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="pill todo" style="font-weight: 700; font-size: 12px;">[{{ $sub->code }}]</span>
                        </td>
                        <td>
                            <code style="background: var(--surface); padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border); font-size: 12px; color: var(--primary);">
                                {{ $sub->code }}-PRJ-{{ str_pad($sub->projects_count + 1, 3, '0', STR_PAD_LEFT) }}
                            </code>
                        </td>
                        <td>
                            <b>{{ $sub->projects_count }}</b> <small class="muted">projects</small>
                        </td>
                        <td>
                            <small class="muted">{{ $sub->azure_company_name ?: 'Manual' }}</small>
                        </td>
                        <td>
                            <span class="pill {{ $sub->status === 'active' ? 'active' : 'on_hold' }}">
                                {{ ucfirst($sub->status) }}
                            </span>
                        </td>
                        <td class="right">
                            <div class="inline-actions" style="justify-content: flex-end;">
                                <button class="icon-btn" type="button" title="Edit Subsidiary" data-show-dialog="edit-sub-{{ $sub->id }}">✎</button>
                                @if($sub->projects_count === 0)
                                    <form method="post" action="{{ route('subsidiaries.destroy', $sub) }}" onsubmit="return AppDialog.confirmAction(event,this,'Delete subsidiary {{ $sub->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="icon-btn" type="submit" title="Delete Subsidiary" style="color: var(--danger);">🗑</button>
                                    </form>
                                @endif
                            </div>

                            <dialog id="edit-sub-{{ $sub->id }}" class="modal">
                                <div class="modal-head">
                                    <h2>Edit Subsidiary</h2>
                                    <button class="icon-btn" type="button" data-close-dialog>×</button>
                                </div>
                                <form method="post" action="{{ route('subsidiaries.update', $sub) }}" class="stack">
                                    @csrf
                                    @method('PUT')
                                    <label>Subsidiary Name <span class="required">*</span>
                                        <input name="name" value="{{ $sub->name }}" required maxlength="160">
                                    </label>
                                    <div class="two-fields">
                                        <label>Project Code Prefix <span class="required">*</span>
                                            <input name="code" value="{{ $sub->code }}" required minlength="2" maxlength="8" style="text-transform: uppercase;">
                                            <small class="muted">Used for project codes, e.g. {{ $sub->code }}-PRJ-001</small>
                                        </label>
                                        <label>Status
                                            <select name="status">
                                                <option value="active" @selected($sub->status === 'active')>Active</option>
                                                <option value="inactive" @selected($sub->status === 'inactive')>Inactive</option>
                                            </select>
                                        </label>
                                    </div>
                                    <label>Azure Company Name (Optional)
                                        <input name="azure_company_name" value="{{ $sub->azure_company_name }}" placeholder="Exact match with Microsoft Entra companyName">
                                    </label>
                                    <label>Description
                                        <textarea name="description" rows="2">{{ $sub->description }}</textarea>
                                    </label>
                                    <div class="form-actions mt-16">
                                        <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">
                            <div class="empty-icon">🏢</div>
                            <b>No subsidiaries created yet</b>
                            <p class="muted">Click "+ Add Subsidiary" or "Check Azure Entra Entities" to define your subsidiaries and their project prefixes.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Subsidiary Dialog -->
<dialog id="add-subsidiary-dialog" class="modal">
    <div class="modal-head">
        <h2>Add Subsidiary</h2>
        <button class="icon-btn" type="button" data-close-dialog>×</button>
    </div>
    <form method="post" action="{{ route('subsidiaries.store') }}" class="stack">
        @csrf
        <label>Subsidiary Name <span class="required">*</span>
            <input name="name" required maxlength="160" placeholder="e.g. GS Technologies">
        </label>
        <label>Project Code Prefix <span class="required">*</span>
            <input name="code" required minlength="2" maxlength="8" placeholder="e.g. GST" style="text-transform: uppercase;">
            <small class="muted">Projects in this subsidiary will generate codes like GST-PRJ-001, GST-PRJ-002.</small>
        </label>
        <label>Azure Company Name (Optional)
            <input name="azure_company_name" placeholder="Exact company name in Azure AD if mapping">
        </label>
        <label>Description
            <textarea name="description" rows="2" placeholder="Brief note about this subsidiary"></textarea>
        </label>
        <div class="form-actions mt-16">
            <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary">Create Subsidiary</button>
        </div>
    </form>
</dialog>
@endsection

