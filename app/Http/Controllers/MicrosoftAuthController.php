<?php
namespace App\Http\Controllers;

use App\Models\{SocialAccount, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;
use Laravel\Socialite\Two\InvalidStateException;

class MicrosoftAuthController {
    public function redirect(Request $request) {
        $callback=(string)config('services.azure.redirect');
        if(!config('services.azure.client_id') || !config('services.azure.client_secret') || !config('services.azure.tenant') || !filter_var($callback,FILTER_VALIDATE_URL)){
            return redirect()->to(route('login',[],false))->withErrors(['email'=>'Microsoft sign-in is not configured. Please contact your PMO administrator.']);
        }
        // Start OAuth on the callback origin so its session cookie returns with state.
        $parts=parse_url($callback);
        $origin=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        if(strcasecmp($request->getSchemeAndHttpHost(),$origin)!==0){
            return redirect()->away($origin.route('azure.redirect',[],false));
        }
        return Socialite::driver('azure')->redirect();
    }

    public function callback(Request $request) {
        if ($request->filled('error')) {
            return redirect()->to(route('login',[],false))->withErrors(['email' => 'Microsoft sign-in was cancelled or denied: ' . $request->input('error_description', $request->input('error'))]);
        }

        try {
            $remote = Socialite::driver('azure')->user();
        } catch (InvalidStateException $e) {
            return redirect()->to(route('login',[],false))->withErrors(['email'=>'Your Microsoft sign-in session expired or changed. Please select Continue with Microsoft again. Use the same browser tab to finish signing in.']);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('login')->withErrors(['email' => 'Microsoft sign-in could not be completed. Please try again or contact your PMO administrator.']);
        }

        $tenant = config('services.azure.tenant');
        $azureId = (string)$remote->getId();

        if($azureId==='')return redirect()->to(route('login',[],false))->withErrors(['email'=>'Microsoft did not return a valid account identity. Please try again.']);

        // 1. Look for existing linked SocialAccount
        $identity = SocialAccount::with('user')
            ->where('provider', 'azure')
            ->where('tenant_id', $tenant)
            ->where('provider_user_id', $azureId)
            ->first();

        if($identity && !$identity->user?->isActive())return redirect()->to(route('login',[],false))->withErrors(['email'=>'Your account is inactive. Please contact your PMO administrator.']);
        if ($identity && $identity->user?->isActive()) {
            Auth::login($identity->user);
            $request->session()->regenerate();
            $request->session()->put('auth_method', 'azure');
            return redirect()->to(route('dashboard',[],false));
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
