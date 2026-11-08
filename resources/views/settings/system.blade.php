@extends('layouts.app')
@section('title','System settings')
@section('content')
<div class="page-head"><div><div class="eyebrow">PMO MANAGEMENT</div><h1>System settings</h1><p class="muted">A simple overview of your application's configuration. Secrets are never shown here.</p></div></div>
<div class="stats"><div class="stat"><span>Users</span><strong>{{ $userCount }}</strong></div><div class="stat"><span>Projects</span><strong>{{ $projectCount }}</strong></div><div class="stat"><span>Mail channel</span><strong class="settings-value">{{ strtoupper($mailDriver) }}</strong></div><div class="stat"><span>Queue</span><strong class="settings-value">{{ strtoupper($queueDriver) }}</strong></div></div>
<section class="card"><div class="card-head"><h2>Integrations</h2></div><div class="list-entry"><div><b>Microsoft Entra</b><small>Sign-in and PMO directory search</small></div><span class="pill {{ $azureConfigured?'active':'pending' }}">{{ $azureConfigured?'Credentials set':'Setup required' }}</span></div><div class="list-entry"><div><b>Mail notifications</b><small>Project Manager assignment emails</small></div><span class="pill {{ $mailDriver==='log'?'pending':'active' }}">{{ $mailDriver==='log'?'Logged locally':'Configured: '.$mailDriver }}</span></div><details class="integration-help"><summary>Integration setup details</summary><p class="help-note">Connection settings are configured in your server's .env file, not in the browser. An app administrator must grant Microsoft Graph directory permissions before Azure search works.</p></details></section>

@endsection
