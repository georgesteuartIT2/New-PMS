@extends('layouts.app')
@section('title', 'My Account')
@section('content')
<!-- Screen 14: Profile / My Account -->
<div class="page-head">
    <div>
        <h1>My Account</h1>
        <p class="muted">Manage your profile and login details.</p>
    </div>

</div>

<div class="card profile-card">
    <div class="profile-avatar-circle">
        {{ strtoupper(substr($user->name, 0, 1)) }}
    </div>

    <h2 style="font-size: 18px; margin-bottom: 4px;">{{ $user->name }}</h2>
    <p class="muted" style="margin-bottom: 16px;">{{ $user->email }}</p>

    <div style="display: inline-flex; gap: 8px; margin-bottom: 24px;">
        <span class="pill {{ $user->isPmo() ? 'active' : 'todo' }}">
            {{ $user->systemRoleLabel() }}
        </span>
        <span class="pill active">Account Active</span>
    </div>

    <hr>

    <div class="stack" style="text-align: left; max-width: 360px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0;">
            <div>
                <b>Sign-in Security</b>
                <small class="block muted">Password status</small>
            </div>
            <span class="pill {{ $user->password ? 'active' : 'todo' }}">
                {{ $user->password ? 'Configured' : 'Microsoft SSO' }}
            </span>
        </div>

        @if($user->password)
            <details class="card" style="margin-top: 10px; background: var(--surface); padding: 14px;">
                <summary style="cursor: pointer; font-weight: 600; color: var(--primary);">Change Password</summary>
                <form method="post" action="{{ route('profile.password') }}" class="stack mt-16">
                    @csrf
                    @method('PATCH')
                    <label>Current Password
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </label>
                    <label>New Password
                        <input type="password" name="password" required minlength="12" autocomplete="new-password">
                    </label>
                    <label>Confirm New Password
                        <input type="password" name="password_confirmation" required autocomplete="new-password">
                    </label>
                    <button class="btn btn-primary btn-sm align-self-start mt-8">Save New Password</button>
                </form>
            </details>
        @endif
    </div>
</div>

<section class="card profile-edit-card" aria-labelledby="profile-edit-title">
    <div class="modal-head">
        <h2 id="profile-edit-title">Profile details</h2>
        
    </div>
    <form method="post" action="{{ route('profile.update') }}" class="stack">
        @csrf
        @method('PATCH')
        <label>Full Name <span class="required">*</span>
            <input name="name" value="{{ old('name', $user->name) }}" required maxlength="120">
        </label>
        <label>Email Address
            <input value="{{ $user->email }}" disabled style="background: var(--surface);">
            <small class="muted">Email changes must be performed by PMO administration.</small>
        </label>
        <div class="form-actions mt-16">
            
            <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
    </form>
</section>
@endsection
