<?php
namespace App\Http\Controllers;
use App\Models\{Project,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class SettingsController {
 public function profile(Request $request){
  $user=$request->user()->load('socialAccounts');
  return view('settings.profile',compact('user'));
 }
 public function updateProfile(Request $request){
  $data=$request->validate(['name'=>'required|string|max:120']);
  $request->user()->update($data);
  return back()->with('success','Profile updated.');
 }
 public function updatePassword(Request $request){
  $user=$request->user();
  if(!$user->password)throw ValidationException::withMessages(['current_password'=>'Your account uses Microsoft sign-in only.']);
  $data=$request->validate(['current_password'=>'required|string','password'=>'required|string|min:12|confirmed']);
  if(!Hash::check($data['current_password'],$user->password))throw ValidationException::withMessages(['current_password'=>'Current password is incorrect.']);
  $user->update(['password'=>$data['password'],'must_change_password'=>false]);
  $request->session()->regenerate();return back()->with('success','Password changed.');
 }
 public function system(Request $request){
  abort_unless($request->user()->isPmo(),403);
  return view('settings.system',['azureConfigured'=>filled(config('services.azure.client_id'))&&filled(config('services.azure.tenant'))&&filled(config('services.azure.client_secret')),'mailDriver'=>config('mail.default'),'queueDriver'=>config('queue.default'),'projectCount'=>Project::count(),'userCount'=>User::count()]);
 }
}
