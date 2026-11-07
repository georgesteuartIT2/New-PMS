@extends('layouts.app')
@section('title', 'Template - ' . $template->name)

@section('content')
<div style="max-width: 860px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Breadcrumb -->
    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7578; margin-bottom: 16px;">
        <a href="{{ route('templates.index') }}" style="color: #6B7578; text-decoration: none;">Templates</a>
        <span>›</span>
        <span style="color: #22272B; font-weight: 600;">{{ $template->name }}</span>
    </div>

    <!-- Page Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">{{ $template->name }}</h1>
                @if($template->isArchived())
                    <span style="font-size: 11px; background: #F1F5F9; color: #64748B; padding: 2px 8px; border-radius: 4px; border: 1px solid #E2E8F0;">Archived</span>
                @endif
            </div>
            <p style="margin: 0; font-size: 13.5px; color: #6B7578;">{{ $template->description ?: 'Reusable project template plan.' }}</p>
        </div>

        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="{{ route('templates.export', $template) }}" class="btn btn-outline btn-sm" style="height: 36px; font-size: 12.5px;">
                Export Excel
            </a>
            @if(!$template->isArchived() && ($canCreateProject ?? true))
                <a href="{{ route('projects.create', ['template_id' => $template->id, 'mode' => 'template']) }}" class="btn btn-primary btn-sm" style="background: #315F58; color: #FFFFFF; height: 36px; font-size: 12.5px; font-weight: 650;">
                    Use Template in Project
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div style="background: #EBF3F1; color: #315F58; border: 1px solid #C2DDD7; border-left: 4px solid #315F58; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13.5px;">
            {{ session('success') }}
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div style="background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--border); border-left: 4px solid var(--danger); padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px; font-size: 13.5px;">
            <b style="display: block; margin-bottom: 4px;">Please review the following inputs:</b>
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Card: Template Details -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
            <h2 style="font-size: 16px; font-weight: 700; color: #22272B; margin: 0;">Template Configuration</h2>
            <button type="button" class="btn btn-primary btn-sm" onclick="openTaskPlanModal()" style="background: #315F58; color: #FFFFFF; font-weight: 650; font-size: 12.5px; height: 34px;">
                View / Edit Task Plan ({{ $template->tasks()->count() }} Tasks) →
            </button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 650; color: #64748B; text-transform: uppercase; margin-bottom: 4px;">
                    Estimated Duration
                </label>
                <div style="font-size: 14px; font-weight: 600; color: #22272B;">
                    {{ $template->formattedDuration() }}
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 650; color: #64748B; text-transform: uppercase; margin-bottom: 4px;">
                    Reference Start Date
                </label>
                <div style="font-size: 14px; font-weight: 600; color: #22272B;">
                    {{ $template->reference_start_date ? $template->reference_start_date->format('d M Y') : 'Not Set' }}
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 650; color: #64748B; text-transform: uppercase; margin-bottom: 4px;">
                    Working Hours
                </label>
                <div style="font-size: 14px; color: #22272B;">
                    {{ $template->working_hours ?: '09:00 - 17:00' }}
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 650; color: #64748B; text-transform: uppercase; margin-bottom: 4px;">
                    Category
                </label>
                <div style="font-size: 14px; color: #22272B;">
                    {{ $template->category ?: 'General' }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shared Smart Task Plan Popup Modal -->
@include('templates.partials.task-plan-modal')

@php
    $templateData = [
        'id' => $template->id,
        'name' => $template->name,
        'description' => $template->description,
        'category' => $template->category,
        'estimated_duration' => $template->estimated_duration,
        'duration_unit' => $template->duration_unit,
        'reference_start_date' => $template->reference_start_date?->format('Y-m-d') ?: date('Y-m-d'),
        'working_hours' => $template->working_hours ?: '09:00 - 17:00',
        'status' => $template->status,
    ];
    $tasksData = $template->tasks->map(function($t) {
        return [
            'id' => $t->id,
            'code' => $t->code,
            'parent_code' => $t->parent_code,
            'title' => $t->title,
            'description' => $t->description,
            'start_date' => $t->start_date?->format('Y-m-d'),
            'start_time' => $t->start_time ?: '09:00',
            'end_date' => $t->end_date?->format('Y-m-d'),
            'end_time' => $t->end_time ?: '17:00',
            'duration_days' => $t->duration_days ?: 1,
            'suggested_role_id' => $t->suggested_role_id,
            'suggested_role_name' => $t->suggestedRole?->name,
            'dependency_code' => $t->dependency_code,
            'priority' => $t->priority ?: 'medium',
        ];
    });
@endphp

<script>
    const tplData = @json($templateData);
    const tasksData = @json($tasksData);

    function openTaskPlanModal() {
        if (window.SmartTaskPlan) {
            @if($canManage ?? false)
                window.SmartTaskPlan.openEdit(tplData, tasksData);
            @else
                window.SmartTaskPlan.openView(tplData, tasksData);
            @endif
        }
    }
</script>
@endsection
