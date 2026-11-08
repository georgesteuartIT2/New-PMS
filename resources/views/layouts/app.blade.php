<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · GS ProjectFlow</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/premium.css') }}?v={{ filemtime(public_path('css/premium.css')) }}">
    @if(request()->routeIs('projects.index'))
        <link rel="stylesheet" href="{{ asset('css/project-library.css') }}?v={{ filemtime(public_path('css/project-library.css')) }}">
    @endif
    @if(request()->routeIs('projects.show'))
        <link rel="stylesheet" href="{{ asset('css/project-workspace.css') }}?v={{ filemtime(public_path('css/project-workspace.css')) }}">
    @endif
    @if(request()->routeIs('pmo.task-monitor', 'tasks.mine'))
        <link rel="stylesheet" href="{{ asset('css/task-monitor.css') }}?v={{ filemtime(public_path('css/task-monitor.css')) }}">
    @endif
    <script defer src="{{ asset('js/app.js') }}"></script>
    <script defer src="{{ asset('js/usability.js') }}"></script>
</head>
<body data-page="{{ request()->route()?->getName() }}">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="app-shell">
        <!-- Sidebar Navigation (Clean Typography, No Icons) -->
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark">GS</span>
                <div>
                    <b>GS ProjectFlow</b>
                    <small>Project Management, Simplified.</small>
                </div>
            </a>

            @php
                $navigationIsPmo = auth()->user()->isPmo();
                $navigationItems = [
                    ['label' => $navigationIsPmo ? 'Dashboard' : 'My Workspace', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'M3 10.5 12 3l9 7.5V21h-6v-7H9v7H3z'],
                    ['label' => $navigationIsPmo ? 'Projects' : 'My Projects', 'url' => route('projects.index'), 'active' => request()->routeIs('projects.*', 'gantt.project', 'roles.*', 'templates.*'), 'icon' => 'M3 7V5a1 1 0 0 1 1-1h6l2 3h8a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z'],
                    ['label' => $navigationIsPmo ? 'Tasks' : 'My Tasks', 'url' => $navigationIsPmo ? route('pmo.task-monitor') : route('tasks.mine'), 'active' => request()->routeIs('tasks.*', 'pmo.task-monitor*', 'team-monitor'), 'icon' => 'M9 6h12M9 12h12M9 18h12M3 6l1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2'],
                    ['label' => 'Calendar', 'url' => route('calendar.index'), 'active' => request()->routeIs('calendar.*'), 'icon' => 'M8 3v4M16 3v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'],
                ];
                if ($navigationIsPmo) {
                    $navigationItems[] = ['label' => 'People', 'url' => route('users.index'), 'active' => request()->routeIs('users.*', 'pmo.team-monitor*'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'];
                    $navigationItems[] = ['label' => 'Administration', 'url' => route('administration.index'), 'active' => request()->routeIs('administration.*', 'role-directory.*', 'subsidiaries.*', 'activity.*', 'settings.system'), 'icon' => 'M4 7h16M4 17h16M8 4v6M16 14v6', 'administration' => true];
                }
            @endphp
            <nav class="nav nav-primary" aria-label="Main navigation">
                @foreach($navigationItems as $navigationItem)
                    <a href="{{ $navigationItem['url'] }}" class="{{ $navigationItem['active'] ? 'active' : '' }} {{ ($navigationItem['administration'] ?? false) ? 'nav-admin' : '' }}" @if($navigationItem['active']) aria-current="page" @endif>
                        <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $navigationItem['icon'] }}"/></svg>
                        <span>{{ $navigationItem['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="sidebar-foot">
                <a href="{{ route('profile.show') }}" style="display: flex; align-items: center; gap: 10px; width: 100%;">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="user-mini">
                        <b>{{ auth()->user()->name }}</b>
                        <small>{{ auth()->user()->isPmo() ? 'PMO Admin' : 'User' }}</small>
                    </div>
                </a>
            </div>
        </aside>

        <div class="mobile-shade" data-close-menu></div>

        <!-- Main Content Area -->
        <main class="main-area">
            <!-- Topbar (Clean Professional SaaS Header) -->
            <header class="topbar">
                <button class="mobile-menu-btn" type="button" data-open-menu aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                    <span class="menu-bar"></span>
                    <span class="menu-bar"></span>
                    <span class="menu-bar"></span>
                </button>

                <!-- Search Bar -->
                <form method="get" action="{{ route('projects.index') }}" class="global-search-wrap">
                    <input type="text" name="q" class="global-search-input" aria-label="Search projects" placeholder="Search projects by name or code..." value="{{ request('q') }}">
                </form>

                <!-- Actions & Profile Chip -->
                <div class="top-actions">
                    <a class="nav-alerts-pill" aria-label="Notifications" title="Notifications" href="{{ route('notifications.index') }}">
                        <span>Notifications</span>
                        @if(auth()->user()->unreadNotifications()->count())
                            <span class="alert-count">{{ auth()->user()->unreadNotifications()->count() }}</span>
                        @endif
                    </a>

                    <!-- User Profile Dropdown -->
                    <div class="user-profile-menu">
                        <button type="button" class="user-chip-btn" id="user-profile-btn" aria-haspopup="true" aria-expanded="false" aria-controls="user-profile-panel">
                            <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <div class="user-chip-info">
                                <b>{{ auth()->user()->name }}</b>
                                <small>{{ auth()->user()->isPmo() ? 'PMO Admin' : 'User' }}</small>
                            </div>
                            <span class="dropdown-caret"></span>
                        </button>

                        <div class="dropdown-panel" id="user-profile-panel">
                            <a href="{{ route('profile.show') }}" class="dropdown-item">My Account</a>
                            <a href="{{ route('notifications.index') }}" class="dropdown-item">Notifications</a>
                            @if(auth()->user()->isPmo())
                                <a href="{{ route('activity.index') }}" class="dropdown-item">Activity Log</a>
                                <a href="{{ route('reports.index') }}" class="dropdown-item">Reports</a>
                                <a href="{{ route('settings.system') }}" class="dropdown-item">Settings</a>
                            @endif
                            <div class="dropdown-divider"></div>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item dropdown-logout" style="width: 100%; text-align: left;">Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content" id="main-content" tabindex="-1">
                @if(session('success'))
                    <div class="alert success" role="status">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert danger" role="alert">
                        <b>Please review the following:</b>
                        <ul style="margin: 4px 0 0; padding-left: 18px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('layouts._section-nav')
                @yield('content')

                <footer class="footer">
                    GS ProjectFlow <span>·</span> Project Management, Simplified.
                </footer>
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
