@extends('layouts.app')
@section('title', 'Templates')

@section('content')
<div class="page-head" style="margin-bottom: 20px;">
    <div>
        <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">Templates</h1>
        <p class="muted" style="margin: 0; font-size: 13.5px;">Create and reuse project plans with predefined tasks and schedules.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <a class="btn btn-outline" href="{{ route('templates.downloadBlank') }}" title="Download standard Excel import template" style="font-size: 13px; height: 38px;">
            Excel Sample
        </a>
        @if($canManage)
            <a class="btn btn-primary" href="{{ route('templates.create') }}" style="background: #315F58; color: #FFFFFF; font-size: 13px; height: 38px; font-weight: 650;">
                + Create Template
            </a>
        @endif
    </div>
</div>

@if($selectForProject ?? false)
    <div style="background: #EBF3F1; border: 1.5px solid #315F58; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: 0 2px 6px rgba(49,95,88,0.06);">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #315F58; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; flex-shrink: 0;">
                ✓
            </div>
            <div>
                <b style="font-size: 15px; color: #22272B; display: block; margin-bottom: 2px;">Select a Template for Your New Project</b>
                <span style="font-size: 13px; color: #475569;">Click any template row below to inspect its tasks and schedule. Then click <strong>Continue with Template</strong> to apply it to your project.</span>
            </div>
        </div>
        <a href="{{ route('projects.create') }}" class="btn btn-outline" style="height: 36px; line-height: 34px; font-size: 13px; font-weight: 600;">
            ← Return to Create Project
        </a>
    </div>
@endif

@if(session('success'))
    <div style="background: #EBF3F1; color: #315F58; border: 1px solid #C2DDD7; border-left: 4px solid #315F58; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13.5px; display: flex; align-items: center; justify-content: space-between;">
        <span>{{ session('success') }}</span>
        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;font-size:16px;">×</button>
    </div>
@endif

@if(isset($errors) && $errors->any())
    <div style="background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--border); border-left: 4px solid var(--danger); padding: 12px 16px; border-radius: var(--radius); margin-bottom: 16px; font-size: 13.5px;">
        @foreach($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
@endif

<!-- Search & Filter Toolbar -->
<div class="card" style="padding: 12px 16px; margin-bottom: 16px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px;">
    <form method="GET" action="{{ route('templates.index') }}" id="template-filter-form" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <!-- Search Input -->
        <div style="position: relative; flex: 1; min-width: 240px;">
            <input 
                type="text" 
                name="search" 
                value="{{ $search }}" 
                placeholder="Search templates by name, description, or category..." 
                style="width: 100%; height: 36px; padding-left: 14px; font-size: 13px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px;"
            >
        </div>

        <!-- Category Filter -->
        <div style="min-width: 180px;">
            <select name="category" onchange="this.form.submit()" style="width: 100%; height: 36px; font-size: 13px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px;">
                <option value="all" @selected($category === '' || $category === 'all')>All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>

        <!-- Status Filter -->
        <div style="min-width: 140px;">
            <select name="status" onchange="this.form.submit()" style="width: 100%; height: 36px; font-size: 13px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px;">
                <option value="active" @selected($status === 'active')>Active ({{ $counts['active'] }})</option>
                <option value="archived" @selected($status === 'archived')>Archived ({{ $counts['archived'] }})</option>
                <option value="all" @selected($status === 'all')>All Status ({{ $counts['all'] }})</option>
            </select>
        </div>

        <button type="submit" class="btn btn-outline btn-sm" style="height: 36px; padding: 0 14px; font-size: 13px;">Filter</button>
        @if($search !== '' || ($category !== '' && $category !== 'all') || $status !== 'active')
            <a href="{{ route('templates.index') }}" class="btn btn-ghost btn-sm" style="height: 36px; line-height: 34px; font-size: 13px; color: #64748B;">Reset</a>
        @endif
    </form>
</div>

<!-- Template Listing Table -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <div class="table-wrap">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #F5F6F7; border-bottom: 1px solid #E5E7EB;">
                    <th style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Template Name</th>
                    <th style="padding: 12px 16px; font-size: 12px; font-weight: 650; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Category</th>
                    <th style="padding: 12px 16px; font-size: 12px; font-weight: 650; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Duration</th>
                    <th style="padding: 12px 16px; font-size: 12px; font-weight: 650; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Tasks</th>
                    <th style="padding: 12px 16px; font-size: 12px; font-weight: 650; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Last Updated</th>
                    <th style="padding: 12px 18px; font-size: 12px; font-weight: 650; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                    @php
                        $subtaskCount = $template->tasks->whereNotNull('parent_code')->count();
                    @endphp
                    <tr 
                        class="template-table-row"
                        style="border-bottom: 1px solid #F1F5F9; transition: background 0.12s ease; cursor: pointer;" 
                        data-template-id="{{ $template->id }}"
                        onclick="openTemplatePlan({{ $template->id }}, '{{ ($selectForProject ?? false) ? 'select' : 'view' }}')"
                        onmouseover="this.style.background='#F8FAFC'" 
                        onmouseout="this.style.background='transparent'"
                    >
                        <!-- Template Name -->
                        <td style="padding: 14px 18px; max-width: 320px;">
                            <div style="display: flex; align-items: baseline; gap: 8px;">
                                <span class="template-name-link" style="font-weight: 650; font-size: 13.5px; color: #22272B;">
                                    {{ $template->name }}
                                </span>
                                @if($template->isArchived())
                                    <span style="font-size: 11px; background: #F1F5F9; color: #64748B; padding: 1px 6px; border-radius: 4px; border: 1px solid #E2E8F0;">Archived</span>
                                @endif
                            </div>
                            <small style="display: block; font-size: 12px; margin-top: 3px; color: #64748B; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px;">
                                {{ $template->description ?: 'Standard reusable project plan structure.' }}
                            </small>
                        </td>

                        <!-- Category -->
                        <td style="padding: 14px 16px; font-size: 13px; color: #334155;">
                            {{ $template->category ?: 'General' }}
                        </td>

                        <!-- Duration -->
                        <td style="padding: 14px 16px;">
                            <div style="font-size: 13px; font-weight: 600; color: #22272B;">
                                {{ $template->formattedDuration() }}
                            </div>
                            <small style="font-size: 11.5px; color: #64748B;">{{ $template->working_hours ?: '09:00 - 17:00' }}</small>
                        </td>

                        <!-- Tasks Count -->
                        <td style="padding: 14px 16px;">
                            <div style="font-size: 13px; font-weight: 600; color: #22272B;">
                                {{ $template->tasks_count }} {{ Str::plural('Task', $template->tasks_count) }}
                            </div>
                            @if($subtaskCount > 0)
                                <small style="font-size: 11px; color: #64748B;">({{ $subtaskCount }} {{ Str::plural('subtask', $subtaskCount) }})</small>
                            @else
                                <small style="font-size: 11px; color: #94A3B8;">Main phases</small>
                            @endif
                        </td>

                        <!-- Last Updated -->
                        <td style="padding: 14px 16px; font-size: 12.5px; color: #64748B;">
                            {{ $template->updated_at->format('d M Y') }}
                        </td>

                        <!-- Actions -->
                        <td style="padding: 14px 18px; text-align: right;" onclick="event.stopPropagation()">
                            <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                @if($selectForProject ?? false)
                                    <button 
                                        type="button" 
                                        class="btn btn-primary btn-sm btn-select-template" 
                                        onclick="event.stopPropagation(); openTemplatePlan({{ $template->id }}, 'select')"
                                        data-create-url="{{ route('projects.create', ['template_id' => $template->id, 'mode' => 'template']) }}"
                                        style="background: #315F58; color: #FFFFFF; font-size: 12px; height: 32px; font-weight: 650; padding: 0 14px; display: inline-flex; align-items: center; gap: 4px; border: none; border-radius: 6px; cursor: pointer;"
                                        title="Preview, customize and continue with this template"
                                    >
                                        Continue with Template →
                                    </button>
                                @elseif(!$template->isArchived() && $canCreateProject)
                                    <a 
                                        href="{{ route('projects.create', ['template_id' => $template->id, 'mode' => 'template']) }}" 
                                        class="btn btn-primary btn-sm" 
                                        style="background: #315F58; color: #FFFFFF; font-size: 12px; height: 30px; font-weight: 600; padding: 0 10px;"
                                        title="Create a new project using this template"
                                        onclick="event.stopPropagation()"
                                    >
                                        Use
                                    </a>
                                @endif

                                @if($canManage && !($selectForProject ?? false))
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm btn-edit-template-popup" 
                                        onclick="event.stopPropagation(); openTemplatePlan({{ $template->id }}, 'edit')"
                                        style="font-size: 12px; height: 30px; padding: 0 10px;"
                                        title="Edit this template in Task Plan popup"
                                    >
                                        Edit
                                    </button>

                                    <!-- More Actions Dropdown -->
                                    <div style="position: relative; display: inline-block;">
                                        <button 
                                            type="button" 
                                            class="btn btn-ghost btn-sm" 
                                            onclick="toggleTemplateMenu(this, event)" 
                                            style="height: 30px; width: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; color: #64748B;"
                                            title="More actions"
                                        >
                                            ⋮
                                        </button>
                                        <div class="template-menu-dropdown" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); min-width: 150px; z-index: 50; padding: 4px 0; text-align: left;">
                                            <a href="{{ route('templates.export', $template) }}" style="display: block; padding: 6px 12px; font-size: 12.5px; color: #22272B; text-decoration: none;">
                                                Export Excel
                                            </a>
                                            <form method="POST" action="{{ route('templates.duplicate', $template) }}" style="margin: 0;">
                                                @csrf
                                                <button type="submit" style="display: block; width: 100%; text-align: left; padding: 6px 12px; font-size: 12.5px; color: #22272B; background: none; border: none; cursor: pointer;">
                                                    Duplicate
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('templates.archive', $template) }}" style="margin: 0;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" style="display: block; width: 100%; text-align: left; padding: 6px 12px; font-size: 12.5px; color: #22272B; background: none; border: none; cursor: pointer;">
                                                    {{ $template->isArchived() ? 'Restore' : 'Archive' }}
                                                </button>
                                            </form>
                                            <hr style="margin: 4px 0; border: none; border-top: 1px solid #E2E8F0;">
                                            <form method="POST" action="{{ route('templates.destroy', $template) }}" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete template \'{{ $template->name }}\'?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" style="display: block; width: 100%; text-align: left; padding: 6px 12px; font-size: 12.5px; color: #DC2626; background: none; border: none; cursor: pointer;">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 40px 20px; text-align: center; color: #64748B;">
                            <div style="font-size: 14px; font-weight: 600; margin-bottom: 6px; color: #22272B;">No templates found</div>
                            <div style="font-size: 12.5px; margin-bottom: 14px;">Try adjusting your search criteria or create a new template.</div>
                            @if($canManage)
                                <a class="btn btn-primary btn-sm" href="{{ route('templates.create') }}" style="background: #315F58; color: #FFFFFF;">
                                    + Create First Template
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($templates->hasPages())
        <div style="padding: 12px 18px; border-top: 1px solid #E5E7EB; background: #FFFFFF;">
            {{ $templates->links() }}
        </div>
    @endif
</div>

<!-- Shared Smart Task Plan Popup Modal -->
@include('templates.partials.task-plan-modal')

<script>
    window.TEMPLATES_CACHE = @json($templatesCache ?? []);

    function openTemplatePlan(templateId, mode) {
        mode = mode || 'view';
        const idStr = String(templateId);
        const cached = window.TEMPLATES_CACHE && window.TEMPLATES_CACHE[idStr];

        if (window.SmartTaskPlan) {
            if (mode === 'select') {
                if (cached && cached.template) {
                    window.SmartTaskPlan.openSelect(cached.template, cached.tasks || []);
                } else {
                    window.SmartTaskPlan.openSelect({ id: templateId }, null);
                }
            } else if (mode === 'edit') {
                if (cached && cached.template) {
                    window.SmartTaskPlan.openEdit(cached.template, cached.tasks || []);
                } else {
                    window.SmartTaskPlan.openEdit({ id: templateId }, null);
                }
            } else {
                if (cached && cached.template) {
                    window.SmartTaskPlan.openView(cached.template, cached.tasks || []);
                } else {
                    window.SmartTaskPlan.openView({ id: templateId }, null);
                }
            }
        } else {
            console.error('SmartTaskPlan modal engine is not ready.');
        }
    }

    function toggleTemplateMenu(btn, e) {
        e.stopPropagation();
        document.querySelectorAll('.template-menu-dropdown').forEach(d => {
            if (d !== btn.nextElementSibling) d.style.display = 'none';
        });
        const dropdown = btn.nextElementSibling;
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', function() {
        document.querySelectorAll('.template-menu-dropdown').forEach(d => d.style.display = 'none');
    });
</script>
@endsection
