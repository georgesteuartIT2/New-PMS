@extends('layouts.app')
@section('title','New project')
@section('content')
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}">
<div class="flow-page">
<a class="text-action" href="{{ route('projects.index') }}">&larr; Projects</a>
<header class="flow-heading"><h1>New project</h1><p>Start with the essentials. Your project lead will prepare the plan after accepting.</p></header>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('projects.initiate') }}" class="flow-card flow-form">@csrf
<h2>Project details</h2>
<label>Project name <span>*</span><input name="name" required maxlength="160" value="{{ old('name') }}" placeholder="e.g. Finance system rollout" autofocus></label>
<label>Subsidiary <span>*</span><select name="subsidiary_id" required id="flow-subsidiary"><option value="">Choose a subsidiary</option>@foreach($subsidiaries as $sub)<option value="{{ $sub->id }}" data-preview="{{ $sub->previewProjectCode() }}" @selected(old('subsidiary_id')==$sub->id)>{{ $sub->name }}</option>@endforeach</select><small>Project code: <b id="flow-code">Assigned automatically on save</b></small></label>
<label>Description<textarea name="description" rows="3" maxlength="3000" placeholder="What should this project deliver?">{{ old('description') }}</textarea></label>
<div class="flow-divider"></div><h2>Who should review first?</h2>
<fieldset class="flow-choices"><legend class="workspace-sr-only">First reviewer</legend><label><input type="radio" name="assignment_type" value="owner" @checked(old('assignment_type','owner')==='owner')><span><b>Project Owner</b><small>Reviews the project and chooses a manager.</small></span></label><label><input type="radio" name="assignment_type" value="pm" @checked(old('assignment_type')==='pm')><span><b>Project Manager</b><small>Accepts the request and prepares the plan.</small></span></label></fieldset>
<label>Assign person <span>*</span><select name="assignee_id" required data-directory-picker data-directory-project=""><option value="">Choose a person</option>@foreach($users as $person)<option value="{{ $person->id }}" @selected(old('assignee_id')==$person->id)>{{ $person->name }} ({{ $person->email }})</option>@endforeach</select><small>Search system users or add a person from Microsoft.</small></label>
<footer class="flow-footer"><a href="{{ route('projects.index') }}" class="btn btn-outline">Cancel</a><button class="btn btn-primary" type="submit">Create &amp; send request</button></footer>
</form></div>
<script>const sub=document.getElementById('flow-subsidiary');function preview(){document.getElementById('flow-code').textContent=sub.selectedOptions[0]?.dataset.preview||'Assigned automatically on save';}sub.addEventListener('change',preview);preview();</script>
@endsection
