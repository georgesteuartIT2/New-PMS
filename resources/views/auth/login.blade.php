@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<div class="login-split-page">
    <!-- Left Hero Pane -->
    <div class="login-hero-pane">
        <div class="login-hero-branding">
            <div class="login-hero-logo">
                <span class="brand-mark">GS</span>
                <div>
                    <b class="login-hero-title">GS ProjectFlow</b>
                    <div class="login-hero-tagline">Project Management, Simplified.</div>
                </div>
            </div>
        </div>

        <!-- Architectural wave graphic matching mockup -->
        <svg class="login-hero-art" viewBox="0 0 500 350" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path opacity="0.18" d="M0 240 C140 180, 260 300, 500 160 L500 350 L0 350 Z" fill="#FFFFFF"/>
            <path opacity="0.25" d="M0 280 C180 200, 320 320, 500 210 L500 350 L0 350 Z" fill="#FFFFFF"/>
            <path opacity="0.32" d="M0 310 C160 270, 340 330, 500 270 L500 350 L0 350 Z" fill="#FFFFFF"/>
            <g opacity="0.12" stroke="#FFFFFF" stroke-width="1.5">
                <line x1="80" y1="200" x2="80" y2="350"/>
                <line x1="160" y1="180" x2="160" y2="350"/>
                <line x1="240" y1="220" x2="240" y2="350"/>
                <line x1="320" y1="190" x2="320" y2="350"/>
                <line x1="400" y1="230" x2="400" y2="350"/>
            </g>
        </svg>
    </div>

    <!-- Right Form Pane -->
    <div class="login-form-pane">
        <div class="login-form-container">
            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account</p>
            </div>

            @if ($errors->any())
                <div class="alert danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="stack">
                @csrf
                <div class="field-group">
                    <input type="email" name="email" id="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="Email">
                </div>

                <div class="field-group" style="position: relative;">
                    <input type="password" name="password" id="login-password" autocomplete="current-password" required placeholder="Password">
                    <button type="button" class="password-toggle-text-btn" data-toggle-password="login-password" aria-label="Toggle password visibility">Show</button>
                </div>

                <div class="login-row-remember">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="#help" onclick="alert('Password resets and account updates are managed centrally by the PMO Administrator.')">Forgot password?</a>
                </div>

                <button class="btn btn-primary btn-wide" type="submit">Sign In</button>
            </form>

            <div class="or-divider">or</div>

            <a class="btn btn-outline btn-wide ms-btn" href="{{ route('azure.redirect') }}">
                <span class="ms-mark"><i></i><i></i><i></i><i></i></span>
                Continue with Microsoft
            </a>

            <p class="help-note center mt-24">
                Your account is provisioned by the Project Management Office (PMO). Contact PMO if you need access.
            </p>
        </div>
    </div>
</div>
@endsection
