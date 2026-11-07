<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController {
 public function loginForm(){return view('auth.login');}
 public function login(Request $request){
  $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
  $email=strtolower($data['email']);$key='login:'.sha1($email.'|'.$request->ip());
  if(RateLimiter::tooManyAttempts($key,5))throw ValidationException::withMessages(['email'=>'Too many attempts. Please try again later.']);
  $user=User::where('email',$email)->first();
  if(!$user||!$user->isActive()||!$user->password||!Auth::attempt(['email'=>$email,'password'=>$data['password']])){
   RateLimiter::hit($key,60);throw ValidationException::withMessages(['email'=>'Invalid email or password.']);
  }
  RateLimiter::clear($key);$request->session()->regenerate();$request->session()->put('auth_method','password');
  return redirect()->intended($user->must_change_password?route('password.change'):route('dashboard'));
 }
 public function passwordForm(){return view('auth.change-password');}
 public function changePassword(Request $request){
  abort_unless($request->user()->must_change_password && $request->session()->get('auth_method')!=='azure',403);
  $data=$request->validate(['password'=>'required|string|min:12|confirmed']);
  $request->user()->update(['password'=>Hash::make($data['password']),'must_change_password'=>false]);
  $request->session()->regenerate();
  return redirect()->route('dashboard')->with('success','Your password has been updated.');
 }
 public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
}
