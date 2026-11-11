@extends('layouts.guest')
@section('title','Sign in')
@section('content')
<link rel="stylesheet" href="{{ asset('css/sign-in.css') }}?v={{ filemtime(public_path('css/sign-in.css')) }}">
<main class="signin-shell">
    <section class="signin-access" aria-labelledby="signin-title"><div class="signin-access-inner">
        <a class="signin-brand signin-mobile-brand" href="{{ route('login') }}"><span class="signin-monogram">GS</span><span>GS ProjectFlow<small>Project workspace</small></span></a>
        <header><h1 id="signin-title">Sign in</h1><p>Welcome back to your project workspace.</p></header>
        @if($errors->any())<div class="signin-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if(session('status'))<p class="signin-status" role="status">{{ session('status') }}</p>@endif
        <a class="signin-microsoft" href="{{ route('azure.redirect') }}"><span class="signin-ms-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Continue with Microsoft</a>
        <div class="signin-divider"><span>or use your email</span></div>
        <form method="post" action="{{ route('login.submit') }}" class="signin-form">@csrf
            <label for="email">Work email<input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required placeholder="you@company.com" @error('email') aria-invalid="true" @enderror></label>
            <label for="login-password">Password<span class="signin-password-wrap"><input type="password" id="login-password" name="password" autocomplete="current-password" required placeholder="Enter your password"><button type="button" data-toggle-password="login-password" aria-label="Show password">Show</button></span></label>
            <label class="signin-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>Remember me</label>
            <button class="signin-submit" type="submit">Sign in <span aria-hidden="true">&rarr;</span></button>
        </form>
        <details class="signin-access-help"><summary>Need help signing in?</summary><p>Contact your PMO administrator for account access or a password reset.</p></details>
        <footer class="signin-access-footer">GS ProjectFlow &middot; Project management, simplified.</footer>
    </div></section>
</main>
@endsection
