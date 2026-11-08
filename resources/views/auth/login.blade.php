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

        <div class="login-hero-copy">
            <span class="login-eyebrow">A clearer way to work</span>
            <h2>Projects,<br>made clear.</h2>
            <p>Plan the work. Bring your team together.<br>Keep every next step in view.</p>
        </div>
        <p class="login-hero-foot">Your workspace for projects, people and progress.</p>
    </div>

    <!-- Right Form Pane -->
    <div class="login-form-pane">
        <div class="login-form-container">
            <a class="login-mobile-brand" href="{{ route('login') }}"><span class="brand-mark">GS</span><b>GS ProjectFlow</b></a>
            <div class="login-header">
                <h1>Welcome back</h1>
                <p>Sign in to your project workspace.</p>
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
                    <label class="login-field-label" for="email">Work email</label><input type="email" name="email" id="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="you@company.com">
                </div>

                <div class="field-group" style="position: relative;">
                    <label class="login-field-label" for="login-password">Password</label><input type="password" name="password" id="login-password" autocomplete="current-password" required placeholder="Password">
                    <button type="button" class="password-toggle-text-btn" data-toggle-password="login-password" aria-label="Toggle password visibility">Show</button>
                </div>

                <div class="login-row-remember">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                </div>

                <button class="btn btn-primary btn-wide" type="submit">Sign In</button>
            </form>

            <div class="or-divider">or</div>

            <a class="btn btn-outline btn-wide ms-btn" href="{{ route('azure.redirect') }}">
                <span class="ms-mark"><i></i><i></i><i></i><i></i></span>
                Continue with Microsoft
            </a>

            <details class="signin-help">
                <summary>Need help signing in?</summary>
                <p>For a password reset or access to this workspace, contact your PMO administrator.</p>
            </details>
        </div>
    </div>
</div>
@endsection
