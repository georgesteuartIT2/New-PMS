@extends('layouts.app')
@section('title','Edit project')
@section('content')
@include('projects._tabs')
<div class="page-head"><div><a href="{{ route('projects.show',$project) }}" class="back-link">← {{ $project->name }}</a><h1>Edit project</h1><p class="muted">Keep the essentials up to date.</p></div></div>

<div class="form-width"><form action="{{ route('projects.update',$project) }}" method="post" class="card form-card stack">@csrf @method('PUT')
<label>Project name<input name="name" required maxlength="160" value="{{ old('name',$project->name) }}"></label><label>Description<textarea name="description" rows="3">{{ old('description',$project->description) }}</textarea></label>
<div class="two-fields"><label>Start date<input type="date" name="starts_on" required value="{{ old('starts_on',$project->starts_on->format('Y-m-d')) }}"></label><label>End date<input type="date" name="ends_on" required value="{{ old('ends_on',$project->ends_on->format('Y-m-d')) }}"></label></div>
<label>Status<select name="status">@foreach(($project->status==='active'?['active'=>'Active','on_hold'=>'On hold','completed'=>'Completed','archived'=>'Archived']:($project->status==='on_hold'?['on_hold'=>'On hold','active'=>'Active','completed'=>'Completed','archived'=>'Archived']:($project->status==='completed'?['completed'=>'Completed','archived'=>'Archived']:[$project->status=>ucwords($project->status)]))) as $status=>$label)<option value="{{ $status }}" @selected($project->status===$status)>{{ $label }}</option>@endforeach</select></label>
<p class="help-note">Only a Project Manager can accept and activate a pending project. Project-level dates and status changes are logged.</p>
<div class="form-actions"><a href="{{ route('projects.show',$project) }}" class="btn btn-ghost">Cancel</a><button class="btn btn-primary">Save project</button></div></form></div>
@endsection
