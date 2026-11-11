@extends('layouts.app')
@section('title','Prepare project plan')
@section('content')
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}">
@php
 $planStarted = $project->plan_initialized || $project->tasks()->exists();
 $templateLibraryUrl = route('templates.index', ['select_for_project' => 1, 'plan_project' => $project->id]);
 $startMode = ($selectedTemplate || old('mode') === 'template') ? 'template' : 'blank';
@endphp
<div class="flow-page"><a href="{{ route('projects.show',$project) }}" class="text-action">&larr; {{ $project->name }}</a>
<header class="flow-heading"><h1>Prepare your project plan</h1><p>Choose your starting point, then add tasks and assign your team in one workspace.</p></header>
@if($errors->any())<p class="alert alert-danger" role="alert">{{ $errors->first() }}</p>@endif
@if($planStarted)<section class="flow-card"><h2>Your plan is already started</h2><p>Continue adding or importing tasks, assign people, then save and notify the team.</p><a class="btn btn-primary" href="{{ route('projects.show',$project) }}#tasks">Continue preparing tasks &rarr;</a></section>
@else<button type="button" class="btn btn-primary" onclick="document.getElementById('plan-start-dialog').showModal()">Choose your plan &rarr;</button>
<dialog id="plan-start-dialog" class="modal flow-plan-dialog" aria-labelledby="plan-start-title">
<header class="flow-plan-title"><div><h2 id="plan-start-title">{{ (int)$project->pm_user_id===auth()->id() ? 'Thank you for accepting!' : 'Prepare the project plan' }}</h2><p>{{ $project->name }} &middot; {{ $project->code }}</p></div><button type="button" class="btn btn-ghost" data-close-dialog aria-label="Close plan setup">&times;</button></header>
<form method="post" id="plan-setup-form" action="{{ route('projects.plan.initialize',$project) }}" class="flow-form" data-library-url="{{ $templateLibraryUrl }}">@csrf
@if($errors->any())<p class="alert alert-danger" role="alert">{{ $errors->first() }}</p>@endif
<h2>Start your plan</h2>
<input type="hidden" name="mode" id="plan-mode" value="{{ $startMode }}">
<input type="hidden" name="project_template_id" id="plan-template-id" value="{{ $selectedTemplate?->id }}" @disabled(!$selectedTemplate)>
<div class="flow-choices" role="group" aria-label="Plan type">
 <button type="button" class="flow-choice {{ $startMode==='blank' ? 'is-selected' : '' }}" data-plan-mode="blank" aria-pressed="{{ $startMode==='blank' ? 'true' : 'false' }}"><span><b>Blank plan</b><small>Add tasks manually or upload Excel.</small></span></button>
 <a class="flow-choice {{ $startMode==='template' ? 'is-selected' : '' }}" href="{{ $templateLibraryUrl }}" data-choose-template><span><b>Use a template</b><small>@if($selectedTemplate){{ $selectedTemplate->name }} &middot; <u>Change</u>@else Pick a saved plan or create one (manual or Excel) &rarr;@endif</small></span></a>
</div>
<div class="flow-grid"><label>Start date <span>*</span><input type="date" name="starts_on" required value="{{ old('starts_on',request('starts_on',today()->format('Y-m-d'))) }}"></label><label>Target finish <span>*</span><input type="date" name="ends_on" required value="{{ old('ends_on',request('ends_on',today()->addWeekdays(10)->format('Y-m-d'))) }}"></label></div>
<label class="flow-budget">Project budget (LKR) <em>Optional</em><input type="number" name="planned_budget" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 500000" value="{{ old('planned_budget',request('planned_budget',$project->planned_budget)) }}"><small>You can change it later in Budget &amp; Closure.</small></label>
<p class="flow-note">No team emails are sent yet. Review tasks and assignments before choosing Save &amp; notify team.</p>
<p id="plan-template-error" role="alert" hidden>Please choose a template, or start with a blank plan.</p>
<footer class="flow-footer"><button type="submit" class="btn btn-primary">Continue to tasks &rarr;</button></footer>
</form></dialog>@endif</div>
@unless($planStarted)<script defer src="{{ asset('js/plan-setup.js') }}?v={{ filemtime(public_path('js/plan-setup.js')) }}"></script>@endunless
@endsection
