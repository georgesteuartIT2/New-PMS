@extends('layouts.app')
@section('title','Prepare project plan')
@section('content')
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}?v={{ filemtime(public_path('css/project-workflow.css')) }}">
@php
 $planStarted = $project->plan_initialized || $project->tasks()->exists();
 $templateLibraryUrl = route('templates.index', ['select_for_project' => 1, 'plan_project' => $project->id]);
 $startMode = old('mode', $selectedTemplate ? 'template' : 'blank');
 $existingTasksCount = $project->tasks()->count();
 $hasTasksReady = $selectedTemplate ? ($selectedTemplate->tasks->count() > 0) : ($existingTasksCount > 0 || !empty(old('blank_plan')));
@endphp
<div class="flow-page"><a href="{{ route('projects.show',$project) }}" class="text-action">&larr; {{ $project->name }}</a>
<header class="flow-heading"><h1>Project plan</h1><p>Prepare tasks, assign your team and publish when ready.</p></header>
<div class="plan-resume-actions"><button type="button" class="btn btn-primary" onclick="document.getElementById('plan-start-dialog').showModal()">Resume plan setup</button><a class="text-action" href="{{ route('projects.show',$project) }}">Back to project</a></div>

<dialog id="plan-start-dialog" class="modal flow-plan-dialog" aria-labelledby="plan-start-title">
<header class="flow-plan-title">
 <div>
  <h2 id="plan-start-title">{{ (int)$project->pm_user_id===auth()->id() ? 'Set up your project' : 'Prepare the project plan' }}</h2>
  <p>{{ $project->name }} &middot; {{ $project->code }}@if($project->subsidiary) &middot; {{ $project->subsidiary->name }}@endif</p>
 </div>
 <button type="button" class="btn btn-ghost" data-close-dialog aria-label="Close plan setup">&times;</button>
</header>

<form method="post" id="plan-setup-form" data-draft-url="{{ route('projects.plan.draft',$project) }}" action="{{ $planStarted ? route('projects.plan.options',$project) : route('projects.plan.initialize',$project) }}" data-plan-started="{{ $planStarted?'1':'0' }}" class="flow-form" data-library-url="{{ $templateLibraryUrl }}">
@csrf
@if($errors->any())<p class="alert alert-danger" role="alert">{{ $errors->first() }}</p>@endif

@if($planStarted)
<section class="flow-prepared-plan plan-subpanel" style="margin-top:0;">
 <div class="plan-subpanel-header">
  <div>
   <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#315F58;">Starting plan</span>
   <h3 style="font-size:16px; margin:4px 0 2px; color:#0F172A; font-weight:700;">{{ $project->template?->name ?? 'Custom Plan' }}</h3>
   <p style="margin:0; font-size:12.5px; color:#64748B;">
    {{ $existingTasksCount }} {{ \Illuminate\Support\Str::plural('task', $existingTasksCount) }} &middot; {{ $project->starts_on?->format('d M Y') }} &ndash; {{ $project->ends_on?->format('d M Y') }}
   </p>
  </div>
  <span class="plan-tasks-badge {{ $existingTasksCount > 0 ? 'is-ready' : '' }}">
   {{ $existingTasksCount > 0 ? $existingTasksCount.' tasks ready' : 'No tasks yet' }}
  </span>
 </div>
 <div class="plan-actions-group">
  <a href="{{ route('templates.downloadBlank', ['name' => $project->name, 'plan_project' => $project->id]) }}" class="plan-action-btn">
   <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
   Download Excel sample
  </a>
  <a href="{{ route('projects.show', $project).'#tasks' }}" class="plan-action-btn is-primary">
   <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
   View &amp; edit tasks on Gantt
  </a>
 </div>
 <small style="color:#64748B; font-size:11.5px;">Your existing tasks are ready. You can review them, assign project roles below, or publish directly.</small>
</section>
@else
<div class="flow-plan-mode-section">
 <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:10px;">
  <h2 style="font-size:16px; margin:0;">Start your plan</h2>
  <span style="font-size:12px; color:#64748B;">Pick a starting method</span>
 </div>
 <input type="hidden" name="mode" id="plan-mode" value="{{ $startMode }}">
 <input type="hidden" name="project_template_id" id="plan-template-id" value="{{ $selectedTemplate?->id }}" @disabled(!$selectedTemplate)>
 <input type="hidden" name="blank_plan" id="blank-plan-data" value="{{ old('blank_plan') }}">

 <div class="flow-choices" role="group" aria-label="Plan type">
  <button type="button" class="flow-choice {{ $startMode==='blank' ? 'is-selected' : '' }}" data-plan-mode="blank" aria-pressed="{{ $startMode==='blank' ? 'true' : 'false' }}">
   <span>
    <b>Blank plan</b>
    <small>Add tasks manually or upload Excel.</small>
   </span>
  </button>
  <a class="flow-choice {{ $startMode==='template' ? 'is-selected' : '' }}" href="{{ $templateLibraryUrl }}" data-choose-template>
   <span>
    <b>Use a template</b>
    <small id="template-card-hint">@if($selectedTemplate){{ $selectedTemplate->name }} &middot; <u>Change</u>@else Pick a saved plan or create one (manual or Excel) &rarr;@endif</small>
   </span>
  </a>
 </div>

 <!-- Blank Plan Interactive Tooling Subpanel -->
 <div id="blank-plan-subpanel" class="plan-subpanel" style="{{ $startMode==='blank' ? '' : 'display:none;' }}">
  <div class="plan-subpanel-header">
   <div>
    <strong style="font-size:13px; color:#1E293B;">Blank plan task tools</strong>
    <p style="margin:2px 0 0; font-size:12px; color:#64748B;">Build your schedule now with full Excel import/export or manual task entry.</p>
   </div>
   <span id="blank-tasks-status" class="plan-tasks-badge {{ !empty(old('blank_plan')) ? 'is-ready' : '' }}">
    {{ !empty(old('blank_plan')) ? 'Tasks prepared from draft' : 'No tasks prepared yet' }}
   </span>
  </div>
  <div class="plan-actions-group">
   <a href="{{ route('templates.downloadBlank', ['name' => $project->name, 'plan_project' => $project->id]) }}" class="plan-action-btn" title="Download Excel template for this project">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Download Excel sample
   </a>
   <button type="button" class="plan-action-btn" id="btn-upload-plan-excel" title="Upload Excel spreadsheet to populate tasks">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Upload Excel plan
   </button>
   <button type="button" class="plan-action-btn is-primary" id="btn-manual-blank-tasks" title="Open task builder to add tasks, durations, and assignees">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    <span id="btn-manual-blank-label">+ Build tasks manually</span>
   </button>
  </div>
  <input type="file" id="plan-excel-file-input" accept=".xlsx,.xls,.csv" style="display:none;">
 </div>

 <!-- Template Subpanel -->
 <div id="template-plan-subpanel" class="plan-template-inline" style="{{ $startMode==='template' ? '' : 'display:none;' }}">
  @if($selectedTemplate)
   <button type="button" class="text-action plan-edit-selected" id="btn-customize-template-tasks">Edit selected tasks &amp; assignees &rarr;</button>
  @else
   <div class="plan-subpanel-header">
    <div>
     <strong style="font-size:13px; color:#1E293B;">Choose a template from library</strong>
     <p style="margin:2px 0 0; font-size:12px; color:#64748B;">Select an active template or create a new one for this project.</p>
    </div>
   </div>
   <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    @if(isset($templates) && $templates->isNotEmpty())
     <select id="quick-template-select" style="max-width:280px; height:36px; padding:0 10px; font-size:12.5px; border:1px solid #CBD5E1; border-radius:7px;">
      <option value="">-- Quick select active template --</option>
      @foreach($templates as $tpl)
       <option value="{{ $tpl->id }}" data-url="{{ route('projects.plan.setup', array_merge(['project' => $project->id, 'template_id' => $tpl->id], request()->only('starts_on', 'ends_on', 'planned_budget')))}}">
        {{ $tpl->name }} ({{ $tpl->tasks_count }} tasks)
       </option>
      @endforeach
     </select>
    @endif
    <a href="{{ $templateLibraryUrl }}" class="plan-action-btn is-primary">
     Browse template library &rarr;
    </a>
    <a href="{{ route('templates.create', ['select_for_project' => 1, 'plan_project' => $project->id]) }}" class="plan-action-btn">
     + Create new template
    </a>
   </div>
  @endif
 </div>

 <div class="flow-grid">
  <label>Start date <span>*</span>
   <input type="date" name="starts_on" id="plan-starts-on" required value="{{ old('starts_on',request('starts_on',today()->format('Y-m-d'))) }}">
  </label>
  <label>
   <div style="display:flex; justify-content:space-between; align-items:baseline;">
    <span>Target finish <span>*</span></span>
    <small id="working-days-badge" style="color:#315F58; font-weight:600; font-size:11.5px;"></small>
   </div>
   <input type="date" name="ends_on" id="plan-ends-on" required value="{{ old('ends_on',request('ends_on',today()->addWeekdays(10)->format('Y-m-d'))) }}">
  </label>
 </div>
</div>
@endif

<section class="plan-project-details"><h2>Project details</h2><label><span class="plan-field-label">Project name <b>*</b></span><input name="name" required maxlength="160" value="{{ old('name',$project->name) }}"></label><label>Description <em>Optional</em><textarea name="description" rows="2" maxlength="3000" placeholder="What will this project deliver?">{{ old('description',$project->description) }}</textarea></label></section>
<label class="flow-budget">Project budget (LKR) <em>Optional</em>
 <input type="number" name="planned_budget" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 500000" value="{{ old('planned_budget',request('planned_budget',$project->planned_budget)) }}">
 <small>You can change it later in Budget &amp; Closure.</small>
</label>

@include('projects._optional-roles',['roleProject'=>$project,'roleSectionTitle'=>'Team & Project roles'])

<p class="flow-note" style="margin:4px 0 0; font-size:12px; color:#64748B;">
 Review tasks and assignees before publishing. Your team is notified only when you publish.
</p>
<p id="plan-template-error" role="alert" hidden style="color:#DC2626; font-size:12.5px; margin:0;">Please choose a template, or start with a blank plan.</p>

<footer class="flow-footer">
 <p class="flow-footer-note"><span id="plan-draft-status" role="status">Changes save as a draft.</span><br>
  Team emails and in-app notifications are sent only when you publish.
 </p>
 <div class="flow-footer-buttons">
  <button type="submit" name="publish_now" value="1" class="btn btn-primary btn-publish-direct" id="btn-publish-direct" @disabled(!$hasTasksReady)>
   <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
   Publish &amp; notify team
  </button>
 </div>
</footer>
</form>
</dialog>
</div>

<!-- Smart Task Plan Engine Modal for in-place creation, Excel import, and template customization -->
@include('templates.partials.task-plan-modal')

<script>
window.PROJECT_TEMPLATE_CONTEXT = {
    select_for_project: 1,
    plan_project: {{ $project->id }},
    starts_on: @json(old('starts_on', request('starts_on', today()->format('Y-m-d')))),
    ends_on: @json(old('ends_on', request('ends_on', today()->addWeekdays(10)->format('Y-m-d')))),
    planned_budget: @json(old('planned_budget', request('planned_budget', $project->planned_budget)))
};
window.PLAN_SETUP_CONFIG = {
    projectId: {{ $project->id }},
    projectName: @json($project->name),
    selectedTemplateId: @json($selectedTemplate?->id),
    selectedTemplateTasks: @json($selectedTemplate?->tasks ?? []),
    roles: @json($roles->values()),
    users: @json($users->values())
};
</script>
<script defer src="{{ asset('js/plan-setup.js') }}?v={{ filemtime(public_path('js/plan-setup.js')) }}"></script>
@endsection
