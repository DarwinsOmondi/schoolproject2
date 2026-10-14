<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f7fb">
    <title>@yield('title', 'Careflow') | Careflow</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/css/app.css')
    @endif
</head>
<body class="app-shell {{ auth()->check() ? '' : 'auth-shell' }}">
    @php
        $currentUser = auth()->user();
        $currentRole = $currentUser?->role->value;
        $navigationLinks = match ($currentRole) {
            'patient' => [
                ['label' => 'Overview', 'route' => 'patient.dashboard', 'icon' => '⌂'],
                ['label' => 'Appointments', 'route' => 'patient.appointments.index', 'icon' => '▦'],
                ['label' => 'Prescriptions', 'route' => 'patient.prescriptions.index', 'icon' => '✚'],
                ['label' => 'Health records', 'route' => 'patient.medical-records.index', 'icon' => '▤'],
            ],
            'doctor' => [
                ['label' => 'Overview', 'route' => 'doctor.dashboard', 'icon' => '⌂'],
                ['label' => 'Appointments', 'route' => 'doctor.appointments.index', 'icon' => '▦'],
                ['label' => 'Prescriptions', 'route' => 'doctor.prescriptions.index', 'icon' => '✚'],
                ['label' => 'Health records', 'route' => 'doctor.medical-records.index', 'icon' => '▤'],
            ],
            'admin' => [
                ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => '⌂'],
                ['label' => 'User management', 'route' => 'admin.users.index', 'icon' => '♧'],
            ],
            default => [],
        };
        $userInitials = $currentUser
            ? collect(explode(' ', trim($currentUser->name)))->filter()->take(2)->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))->implode('')
            : null;
    @endphp

    @auth
        <div class="app-frame">
            <aside class="app-sidebar">
                <a class="brand-lockup" href="{{ route($currentRole.'.dashboard') }}">
                    <span class="brand-symbol">+</span>
                    <span class="brand-copy"><strong>careflow</strong><small>HEALTH, IN GOOD HANDS</small></span>
                </a>

                <div class="sidebar-section-label">WORKSPACE</div>
                <nav class="primary-navigation" aria-label="Primary navigation">
                    @foreach ($navigationLinks as $link)
                        <a class="primary-navigation-link {{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}">
                            <span class="navigation-icon" aria-hidden="true">{{ $link['icon'] }}</span>
                            <span>{{ $link['label'] }}</span>
                            @if ($link['route'] === $currentRole.'.appointments.index' && isset($pendingAppointmentCount) && $pendingAppointmentCount > 0)
                                <span class="navigation-count">{{ $pendingAppointmentCount }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="sidebar-bottom">
                    <div class="help-card">
                        <span class="help-card-icon" aria-hidden="true">✳</span>
                        <strong>Here for your health</strong>
                        <span>Your care, organized in one place.</span>
                    </div>
                    <a class="sidebar-profile-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                        <span class="avatar avatar-sm">{{ $userInitials }}</span>
                        <span class="profile-copy"><strong>{{ $currentUser->name }}</strong><small>{{ ucfirst($currentRole) }} account</small></span>
                        <span class="profile-more" aria-hidden="true">···</span>
                    </a>
                </div>
            </aside>

            <div class="app-main-column">
                <header class="app-topbar">
                    <div class="topbar-context"><span>YOUR PERSONAL CARE SPACE</span><strong>{{ ucfirst($currentRole) }} portal</strong></div>
                    <div class="topbar-actions">
                        <a class="notification-link" href="{{ route('notifications.index') }}" aria-label="Notifications">
                            <span aria-hidden="true">♧</span>
                            <span>Notifications</span>
                            @if ($unreadNotificationCount > 0)
                                <span class="notification-count">{{ $unreadNotificationCount }}</span>
                            @endif
                        </a>
                        <span class="topbar-divider"></span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="sign-out-button" type="submit"><span aria-hidden="true">↗</span><span>Sign out</span></button>
                        </form>
                    </div>
                </header>
                <main class="app-content">
                    @if (session('status'))
                        <div class="alert app-alert app-alert-success" role="status"><span aria-hidden="true">✓</span><span>{{ session('status') }}</span></div>
                    @endif
                    @if ($errors->any())
                        <div class="alert app-alert app-alert-danger" role="alert">
                            <span aria-hidden="true">!</span>
                            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @yield('content')
                    <footer class="app-footer">
                        <span>Careflow helps keep your care connected.</span>
                        <span>{{ config('app.timezone') }} time</span>
                    </footer>
                </main>
            </div>
        </div>
    @else
        <main class="auth-content">
            @if ($errors->any())
                <div class="alert app-alert app-alert-danger auth-alert" role="alert">
                    <span aria-hidden="true">!</span>
                    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    @endauth
</body>
</html>
