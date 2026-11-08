@extends('layouts.app')
@section('title', 'Administration')
@section('content')
<div class="page-head">
    <div>
        <h1>Administration</h1>
        <p class="muted">Manage access, companies and system setup.</p>
    </div>
</div>
<div class="admin-destinations">
    <a class="admin-destination" href="{{ route('role-directory.index') }}">
        <div><h2>Roles &amp; access</h2><p>Set project responsibilities and what each role can do.</p></div><span aria-hidden="true">&rarr;</span>
    </a>
    <a class="admin-destination" href="{{ route('subsidiaries.index') }}">
        <div><h2>Subsidiaries</h2><p>Manage group companies and their project codes.</p></div><span aria-hidden="true">&rarr;</span>
    </a>
    <a class="admin-destination" href="{{ route('activity.index') }}">
        <div><h2>Activity log</h2><p>See who changed projects, tasks and system settings.</p></div><span aria-hidden="true">&rarr;</span>
    </a>
    <a class="admin-destination" href="{{ route('settings.system') }}">
        <div><h2>Integrations</h2><p>Check Microsoft sign-in and notification setup.</p></div><span aria-hidden="true">&rarr;</span>
    </a>
</div>
@endsection