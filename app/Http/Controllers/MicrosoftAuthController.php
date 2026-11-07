<?php
namespace App\Http\Controllers;

use App\Models\{SocialAccount, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController {
    public function redirect() {
        abort_unless(config('services.azure.client_id') && config('services.azure.tenant'), 503, 'Microsoft sign-in has not been configured in .env.');
        return Socialite::driver('azure')->redirect();
    }

    public function callback(Request $request) {
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors(['email' => 'Microsoft sign-in was cancelled or denied: ' . $request->input('error_description', $request->input('error'))]);
        }

        try {
            $remote = Socialite::driver('azure')->user();
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('login')->withErrors(['email' => 'Microsoft authentication failed: ' . $e->getMessage()]);
        }

        $tenant = config('services.azure.tenant');
        $azureId = (string)$remote->getId();

        // 1. Look for existing linked SocialAccount
        $identity = SocialAccount::with('user')
            ->where('provider', 'azure')
            ->where('tenant_id', $tenant)
            ->where('provider_user_id', $azureId)
            ->first();

        if ($identity && $identity->user?->isActive()) {
            Auth::login($identity->user);
            $request->session()->regenerate();
            $request->session()->put('auth_method', 'azure');
            return redirect()->route('dashboard');
        }

        // 2. If not linked yet, check for existing active user with matching email
        $email = strtolower(trim($remote->getEmail() ?: ($remote->user['mail'] ?? ($remote->user['userPrincipalName'] ?? ''))));
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user && $user->isActive()) {
                SocialAccount::updateOrCreate(
                    ['user_id' => $user->id, 'provider' => 'azure'],
                    ['tenant_id' => $tenant, 'provider_user_id' => $azureId]
                );
                Auth::login($user);
                $request->session()->regenerate();
                $request->session()->put('auth_method', 'azure');
                return redirect()->route('dashboard');
            }
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Access not granted for Microsoft account (' . ($email ?: 'Unknown') . '). Please ask your PMO Administrator to add your account.'
        ]);
    }
}
