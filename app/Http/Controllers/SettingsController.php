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
 public function system(Request $request,\App\Services\EmailConfigurationService $email){
  abort_unless($request->user()->isPmo(),403);
  $configuration=$email->current();$hasPassword=!empty($configuration['password']);unset($configuration['password']);
  return view('settings.system',compact('configuration','hasPassword'));
 }
 public function updateEmail(Request $request,\App\Services\EmailConfigurationService $email){
  abort_unless($request->user()->isPmo(),403);
  $data=$request->validate(['mailer'=>'required|in:smtp,log','host'=>'required_if:mailer,smtp|nullable|string|max:255|not_regex:/[\\r\\n]/','port'=>'required|integer|min:1|max:65535','scheme'=>'required|in:smtp,smtps','username'=>'nullable|string|max:255','password'=>'nullable|string|max:1000','clear_password'=>'sometimes|boolean','from_address'=>'required|email|max:255','from_name'=>'required|string|max:120']);
  $email->save($data);\App\Services\Audit::record('settings.email_updated',$request->user(),['mailer'=>$data['mailer'],'host'=>$data['host']??null,'from_address'=>$data['from_address']]);
  return back()->with('success','Email configuration saved.');
 }
 public function testEmail(Request $request,\App\Services\EmailConfigurationService $email){
  abort_unless($request->user()->isPmo(),403);$data=$request->validate(['recipient'=>'required|email|max:255']);$email->apply();
  if(config('mail.default')!=='smtp')throw ValidationException::withMessages(['recipient'=>'Save an SMTP configuration before sending a test email.']);
  try{\Illuminate\Support\Facades\Mail::raw('Your GS ProjectFlow email configuration is working.',fn($message)=>$message->to($data['recipient'])->subject('GS ProjectFlow: Email configuration test'));}
  catch(\Throwable $error){report($error);throw ValidationException::withMessages(['recipient'=>'Email could not be sent. Check the saved server, port and credentials.']);}
  return back()->with('success','Test email sent to '.$data['recipient'].'.');
 }
}
