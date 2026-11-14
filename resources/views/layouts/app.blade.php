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
    @if(request()->routeIs('pmo.task-monitor', 'tasks.mine', 'monitoring.index'))
        <link rel="stylesheet" href="{{ asset('css/task-monitor.css') }}?v={{ filemtime(public_path('css/task-monitor.css')) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/system.css') }}?v={{ filemtime(public_path('css/system.css')) }}">
    @if(request()->routeIs('projects.create'))
    <link rel="stylesheet" href="{{ asset('css/project-create.css') }}?v={{ filemtime(public_path('css/project-create.css')) }}">
    <script defer src="{{ asset('js/project-create-accessibility.js') }}?v={{ filemtime(public_path('js/project-create-accessibility.js')) }}"></script>
    @endif
    @if(request()->routeIs('monitoring.index','pmo.team-monitor'))
    <link rel="stylesheet" href="{{ asset('css/monitoring.css') }}?v={{ filemtime(public_path('css/monitoring.css')) }}">
    <script defer src="{{ asset('js/monitoring.js') }}?v={{ filemtime(public_path('js/monitoring.js')) }}"></script>
    @endif
    <script defer src="{{ asset('js/system.js') }}?v={{ filemtime(public_path('js/system.js')) }}"></script>
    <script defer src="{{ asset('js/app.js') }}"></script>
    <script defer src="{{ asset('js/usability.js') }}"></script>
    <script defer src="{{ asset('js/task-navigation.js') }}?v={{ filemtime(public_path('js/task-navigation.js')) }}"></script>
    <script defer src="{{ asset('js/task-progress.js') }}?v={{ filemtime(public_path('js/task-progress.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('css/critical-explanation.css') }}?v={{ filemtime(public_path('css/critical-explanation.css')) }}">
    <script defer src="{{ asset('js/critical-explanation.js') }}?v={{ filemtime(public_path('js/critical-explanation.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('css/app-dialog.css') }}?v={{ filemtime(public_path('css/app-dialog.css')) }}">
    <script src="{{ asset('js/app-dialog.js') }}?v={{ filemtime(public_path('js/app-dialog.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('css/interface-system.css') }}?v={{ filemtime(public_path('css/interface-system.css')) }}">
</head>
<body data-page="{{ request()->route()?->getName() }}">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="app-shell">
        <!-- Sidebar Navigation (Clean Typography, No Icons) -->
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark" style="display: inline-flex; align-items: center; justify-content: center;">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 12 12 17 22 12"></polyline>
                        <polyline points="2 17 12 22 22 17"></polyline>
                    </svg>
                </span>
                <div>
                    <b>GS ProjectFlow</b>
                    <small>Project workspace</small>
                </div>
            </a>

            @php
                $navigationIsPmo = auth()->user()->canViewPortfolio();
                if ($navigationIsPmo) {
                    $navigationItems = [
                        ['label' => 'Dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'M3 10.5 12 3l9 7.5V21h-6v-7H9v7H3z'],
                        ['label' => 'Projects', 'url' => route('projects.index'), 'active' => request()->routeIs('projects.*', 'gantt.project', 'roles.*'), 'icon' => 'M3 7V5a1 1 0 0 1 1-1h6l2 3h8a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z'],
                        ['label' => 'Project templates', 'url' => route('templates.index'), 'active' => request()->routeIs('templates.*'), 'icon' => 'M4 3h12l4 4v14H4zM14 3v6h6M8 13h8M8 17h6'],
                        ['label' => 'Task Monitor', 'url' => route('pmo.task-monitor'), 'active' => request()->routeIs('pmo.task-monitor*'), 'icon' => 'M9 6h12M9 12h12M9 18h12M3 6l1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2'],
                        ['label' => 'Team Monitor', 'url' => route('pmo.team-monitor'), 'active' => request()->routeIs('pmo.team-monitor*', 'team-monitor*'), 'icon' => 'M4 19V9M10 19V5M16 19v-7M22 21H2'],
                        ['label' => 'Calendar', 'url' => route('calendar.index'), 'active' => request()->routeIs('calendar.*'), 'icon' => 'M8 3v4M16 3v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'],
                        ['label' => 'People', 'url' => route('users.index'), 'active' => request()->routeIs('users.*'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'],
                        ['label' => 'Activity log', 'url' => route('activity.index'), 'active' => request()->routeIs('activity.*'), 'icon' => 'M9 5H5v16h14V5h-4M9 3h6v4H9zM8 12h8M8 16h5'],
                    ];
                    if (auth()->user()->isPmo()) {
                        $navigationItems[] = [
                            'label' => 'Settings',
                            'url' => route('settings.system'),
                            'active' => request()->routeIs('settings.*'),
                            'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z',
                        ];
                        $navigationItems[] = [
                            'label' => 'Administration',
                            'url' => route('administration.index'),
                            'active' => request()->routeIs('administration.*', 'role-directory.*', 'subsidiaries.*'),
                            'icon' => 'M4 7h16M4 17h16M8 4v6M16 14v6',
                            'administration' => true,
                        ];
                    }
                } else {
                    $navigationItems = [
                        ['label' => 'My Workspace', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'M3 10.5 12 3l9 7.5V21h-6v-7H9v7H3z'],
                        ['label' => 'My Projects', 'url' => route('projects.index'), 'active' => request()->routeIs('projects.*', 'gantt.project', 'roles.*'), 'icon' => 'M3 7V5a1 1 0 0 1 1-1h6l2 3h8a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z'],
                        ['label' => 'All Tasks', 'url' => route('tasks.mine'), 'active' => request()->routeIs('tasks.*'), 'icon' => 'M9 6h12M9 12h12M9 18h12M3 6l1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2'],
                        ['label' => 'Team Monitor', 'url' => route('team-monitor'), 'active' => request()->routeIs('team-monitor*'), 'icon' => 'M4 19V9M10 19V5M16 19v-7M22 21H2'],
                        ['label' => 'Calendar', 'url' => route('calendar.index'), 'active' => request()->routeIs('calendar.*'), 'icon' => 'M8 3v4M16 3v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'],
                    ];
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
                        <small>{{ auth()->user()->systemRoleLabel() }}</small>
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
                    @php($headerUnreadCount = auth()->user()->unreadNotifications()->count())
                    <a class="nav-alerts-pill notification-bell" aria-label="Notifications, {{ $headerUnreadCount }} unread" title="Notifications" href="{{ route('notifications.index') }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif>
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                        @if($headerUnreadCount > 0)<span class="notification-bell-count" aria-hidden="true">{{ $headerUnreadCount > 99 ? '99+' : $headerUnreadCount }}</span>@endif
                    </a>

                    <!-- User Profile Dropdown -->
                    <div class="user-profile-menu">
                        <button type="button" class="user-chip-btn" id="user-profile-btn" aria-haspopup="true" aria-expanded="false" aria-controls="user-profile-panel">
                            <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <div class="user-chip-info">
                                <b>{{ auth()->user()->name }}</b>
                                <small>{{ auth()->user()->systemRoleLabel() }}</small>
                            </div>
                            <span class="dropdown-caret"></span>
                        </button>

                        <div class="dropdown-panel" id="user-profile-panel">
                            <a href="{{ route('profile.show') }}" class="dropdown-item">My Account</a>
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

                @if(auth()->user()->isAdminViewer())<div class="viewer-notice"><span>Admin Viewer</span> Read-only portfolio access</div>@endif
                @include('layouts._section-nav')
                @yield('content')

                <footer class="footer">
                    GS ProjectFlow <span>·</span> Project Management, Simplified.
                </footer>
            </div>
        </main>
    </div>
    @stack('scripts')
<script id="directory-picker-config" defer src="{{ asset('js/directory-picker.js') }}?v={{ filemtime(public_path('js/directory-picker.js')) }}" data-search-url="{{ route('users.azure') }}" data-add-url="{{ route('users.azure.add') }}"></script>
</body>
</html>
