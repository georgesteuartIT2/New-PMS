@extends('layouts.guest')
@section('title', 'Set your password')
@section('content')
<div style="min-height: 100vh; display: grid; place-items: center; background: #F8FAFB; padding: 24px;">
    <div class="card" style="width: 100%; max-width: 420px; padding: 32px;">
        <div class="brand center mb-16" style="justify-content: center;">
            <span class="brand-mark">▤</span>
            <b>GS ProjectFlow</b>
        </div>
        <h1 class="center mt-8" style="font-size: 20px;">Choose a new password</h1>
        <p class="muted center text-sm mt-8">Your temporary password was accepted. Please choose a secure password to activate your account.</p>

        @if ($errors->any())
            <div class="alert danger mt-16" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('password.update') }}" method="post" class="stack mt-24">
            @csrf
            <label>New password
                <input name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="At least 12 characters">
            </label>
            <label>Confirm password
                <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Repeat your password">
            </label>
            <button type="submit" class="btn btn-primary btn-wide mt-8">Save password and continue</button>
        </form>

        <form method="post" action="{{ route('logout') }}" class="center mt-16">
            @csrf
            <button class="btn btn-ghost btn-sm">Sign out</button>
        </form>
    </div>
</div>
@endsection
