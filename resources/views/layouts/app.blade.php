<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · GS ProjectFlow</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
</head>
<body>
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

            <nav class="nav">
                @if(auth()->user()->isPmo())
                    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <span>Dashboard</span>
                    </a>
                    <a class="{{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">
                        <span>Projects</span>
                    </a>
                    <a class="{{ request()->routeIs('subsidiaries.*') ? 'active' : '' }}" href="{{ route('subsidiaries.index') }}">
                        <span>Subsidiaries</span>
                    </a>
                    <a class="{{ request()->routeIs('templates.*') ? 'active' : '' }}" href="{{ route('templates.index') }}">
                        <span>Templates</span>
                    </a>
                    <a class="{{ request()->routeIs('role-directory.*') ? 'active' : '' }}" href="{{ route('role-directory.index') }}">
                        <span>Roles & Permissions</span>
                    </a>
                    <a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <span>Users</span>
                    </a>
                    <a class="{{ request()->routeIs('pmo.task-monitor*') ? 'active' : '' }}" href="{{ route('pmo.task-monitor') }}">
                        <span>Task Monitor</span>
                    </a>
                    <a class="{{ request()->routeIs('pmo.team-monitor*') ? 'active' : '' }}" href="{{ route('pmo.team-monitor') }}">
                        <span>Team Monitor</span>
                    </a>
                    <a class="{{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.system') }}">
                        <span>Settings</span>
                    </a>
                @else
                    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <span>My Workspace</span>
                    </a>
                    <a class="{{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">
                        <span>My Projects</span>
                    </a>
                    <a class="{{ (request()->routeIs('tasks.mine') && request('tab') !== 'team') || request()->routeIs('tasks.show') ? 'active' : '' }}" href="{{ route('tasks.mine') }}">
                        <span>My Tasks</span>
                    </a>
                    <a class="{{ (request()->routeIs('tasks.mine') && request('tab') === 'team') || request()->routeIs('team-monitor*') ? 'active' : '' }}" href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'team']) }}">
                        <span>Team Monitor</span>
                    </a>
                    <a class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>Notifications</span>
                        @php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
                        @if($unreadCount > 0)
                            <span class="status-pill pill-warning" style="font-size: 11px; padding: 1px 7px;">{{ $unreadCount }}</span>
                        @endif
                    </a>
                @endif
            </nav>

            <div class="sidebar-foot">
                <a href="{{ route('profile.show') }}" style="display: flex; align-items: center; gap: 10px; width: 100%;">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="user-mini">
                        <b>{{ auth()->user()->name }}</b>
                        <small>{{ auth()->user()->isPmo() ? 'PMO Admin' : (auth()->user()->pmProjects()->exists() ? 'Project Manager' : 'Team Member') }}</small>
                    </div>
                </a>
            </div>
        </aside>

        <div class="mobile-shade" data-close-menu></div>

        <!-- Main Content Area -->
        <main class="main-area">
            <!-- Topbar (Clean Professional SaaS Header) -->
            <header class="topbar">
                <button class="mobile-menu-btn" type="button" data-open-menu aria-label="Open menu">
                    <span class="menu-bar"></span>
                    <span class="menu-bar"></span>
                    <span class="menu-bar"></span>
                </button>

                <!-- Search Bar -->
                <form method="get" action="{{ route('projects.index') }}" class="global-search-wrap">
                    <input type="text" name="q" class="global-search-input" placeholder="Search projects, tasks, users..." value="{{ request('q') }}">
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
                        <button type="button" class="user-chip-btn" id="user-profile-btn" aria-haspopup="true">
                            <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <div class="user-chip-info">
                                <b>{{ auth()->user()->name }}</b>
                                <small>{{ auth()->user()->isPmo() ? 'PMO Admin' : (auth()->user()->pmProjects()->exists() ? 'Project Manager' : 'Team Member') }}</small>
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

            <div class="content">
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
