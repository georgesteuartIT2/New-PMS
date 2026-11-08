@php
    $sectionItems = [];
    if (auth()->user()->isPmo()) {
        if (request()->routeIs('templates.*')) {
            $sectionItems = [
                ['label' => 'Projects', 'url' => route('projects.index'), 'active' => request()->routeIs('projects.index')],
                ['label' => 'Templates', 'url' => route('templates.index'), 'active' => request()->routeIs('templates.*')],
            ];
        } elseif (request()->routeIs('users.*', 'pmo.team-monitor*')) {
            $sectionItems = [
                ['label' => 'People', 'url' => route('users.index'), 'active' => request()->routeIs('users.*')],
                ['label' => 'Workload', 'url' => route('pmo.team-monitor'), 'active' => request()->routeIs('pmo.team-monitor*')],
            ];
        } elseif (request()->routeIs('administration.*', 'role-directory.*', 'subsidiaries.*', 'activity.*', 'settings.system')) {
            $sectionItems = [
                ['label' => 'Overview', 'url' => route('administration.index'), 'active' => request()->routeIs('administration.*')],
                ['label' => 'Roles & access', 'url' => route('role-directory.index'), 'active' => request()->routeIs('role-directory.*')],
                ['label' => 'Subsidiaries', 'url' => route('subsidiaries.index'), 'active' => request()->routeIs('subsidiaries.*')],
                ['label' => 'Activity log', 'url' => route('activity.index'), 'active' => request()->routeIs('activity.*')],
                ['label' => 'Integrations', 'url' => route('settings.system'), 'active' => request()->routeIs('settings.system')],
            ];
        }
    }
@endphp
@if(count($sectionItems) > 1)
    <nav class="section-nav" aria-label="Section navigation">
        @foreach($sectionItems as $sectionItem)
            <a href="{{ $sectionItem['url'] }}" class="{{ $sectionItem['active'] ? 'active' : '' }}" @if($sectionItem['active']) aria-current="page" @endif>{{ $sectionItem['label'] }}</a>
        @endforeach
    </nav>
@endif