@extends('layouts.app')
@section('title', 'Project Closure · ' . $project->name)
@section('content')
@include('projects._tabs')
<div class="page-head">
    <div>
        <div class="breadcrumb-trail">
            <a href="{{ route('projects.index') }}">Projects</a>
            <span class="crumb-sep">›</span>
            <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
            <span class="crumb-sep">›</span>
            <span>Closure</span>
        </div>
        <h1 class="page-title mt-8">Project Closure & Sign-off</h1>
        <p class="muted">Submit the project for final approval and completion.</p>
    </div>
</div>



<div class="form-width">
    <form method="post" action="{{ route('projects.closure.submit', $project) }}" class="card form-card stack">
        @csrf
        <div class="closure-checklist stack">
            <div class="form-section-title">Completion checklist</div>
            <label class="custom-checkbox-row">
                <input type="checkbox" name="tasks_completed" value="1" {{ $project->progress >= 100 ? 'checked' : '' }}>
                <span>All project tasks are completed.</span>
            </label>
            <label class="custom-checkbox-row">
                <input type="checkbox" name="deliverables_submitted" value="1" checked>
                <span>Deliverables are submitted.</span>
            </label>
            <label class="custom-checkbox-row">
                <input type="checkbox" name="approval_obtained" value="1">
                <span>Client / Sponsor approval is obtained.</span>
            </label>
        </div>

        <label class="mt-16">Closure Notes
            <textarea name="notes" rows="4" placeholder="Enter closure notes..." class="form-textarea"></textarea>
        </label>

        <div class="form-actions mt-24">
            <button type="submit" name="action" value="draft" class="btn btn-outline">Save Draft</button>
            <button type="submit" name="action" value="request" class="btn btn-primary">Request Closure</button>
        </div>
    </form>
</div>
@endsection

