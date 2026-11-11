@php
    $sectionItems = [];
    if (auth()->user()->isPmo()) {
        if (request()->routeIs('administration.*', 'role-directory.*', 'subsidiaries.*')) {
            $sectionItems = [
                ['label' => 'Overview', 'url' => route('administration.index'), 'active' => request()->routeIs('administration.*')],
                ['label' => 'Roles & access', 'url' => route('role-directory.index'), 'active' => request()->routeIs('role-directory.*')],
                ['label' => 'Subsidiaries', 'url' => route('subsidiaries.index'), 'active' => request()->routeIs('subsidiaries.*')],
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