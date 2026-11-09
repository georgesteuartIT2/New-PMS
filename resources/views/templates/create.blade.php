@extends('layouts.app')
@section('title', 'New template')
@section('content')
@php
    $projectReturnContext=array_filter(request()->only('project_flow','draft'),fn($value)=>$value!==null && $value!=='');
    $templateSelectionContext=($selectForProject ?? request()->boolean('select_for_project')) ? array_merge($projectReturnContext,['select_for_project'=>1]) : [];
@endphp
@include('templates._selection-context')
<link rel="stylesheet" href="{{ asset('css/template-create.css') }}?v={{ filemtime(public_path('css/template-create.css')) }}">
<div class="template-create-workspace">
    <a class="tc-back" href="{{ route('templates.index',$templateSelectionContext) }}">&larr; Templates</a>
    <div class="page-head"><div><h1>New template</h1><p class="muted">Set the working schedule, then build a reusable task plan.</p></div></div>
    <section class="tc-card" aria-labelledby="template-setup-title">
        <header class="tc-heading"><h2 id="template-setup-title">Template setup</h2><span>Required fields marked *</span></header>
        <form id="create-template-form">
            <div class="tc-fields">
                <label class="tc-name" for="create_template_name">Template name <span class="tc-required">*</span>
                    <input type="text" id="create_template_name" name="name" value="{{ old('name','') }}" placeholder="e.g. Network infrastructure upgrade" required maxlength="200" autofocus>
                </label>
                <div class="tc-duration">
                    <label for="create_duration">Estimated duration (Working days) <span class="tc-required">*</span></label>
                    <div class="tc-days-input"><input type="number" id="create_duration" name="estimated_duration" value="{{ old('estimated_duration',14) }}" min="1" max="3650" step="1" required aria-describedby="duration-policy duration-hint"><span>Days</span></div>
                    <small id="duration-policy">Excludes Saturdays &amp; Sundays.</small>
                    <p id="duration-hint" class="tc-duration-hint" aria-live="polite"></p>
                </div>
                <label for="create_reference_start_date">Reference start date <span class="tc-required">*</span>
                    <input type="date" id="create_reference_start_date" name="reference_start_date" value="{{ old('reference_start_date',date('Y-m-d')) }}" required aria-describedby="reference-date-help">
                    <small id="reference-date-help">Dates shift to the actual start date when a project is created.</small>
                </label>
                <label for="create_working_hours">Working hours
                    <select id="create_working_hours" name="working_hours">
                        @foreach(['09:00 - 17:00'=>'09:00 AM - 05:00 PM','08:30 - 17:00'=>'08:30 AM - 05:00 PM','08:00 - 16:30'=>'08:00 AM - 04:30 PM','09:00 - 18:00'=>'09:00 AM - 06:00 PM'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('working_hours','09:00 - 17:00')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="tc-schedule"><span>Reference schedule</span><strong id="create-schedule-text" aria-live="polite"></strong></div>
            <p id="template-create-error" class="tc-error" role="alert" hidden></p>
            <footer class="tc-footer"><div><a class="btn btn-outline" href="{{ route('templates.index',$templateSelectionContext) }}">Cancel</a><button type="submit" id="btn-create-task-plan" class="btn btn-primary">Build task plan &rarr;</button></div></footer>
        </form>
    </section>
    <p class="tc-next">Next: review tasks, dependencies and project roles before saving your template.</p>
</div>
@include('templates.partials.task-plan-modal')
<script src="{{ asset('js/template-create.js') }}?v={{ filemtime(public_path('js/template-create.js')) }}"></script>
@endsection
