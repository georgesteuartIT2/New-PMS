<?php
namespace App\Http\Controllers;
use App\Models\{User,SocialAccount};
use App\Services\AzureGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
class UserLinkController {
 public function link(Request $request,AzureGraphService $graph){
  $data=$request->validate(['user_id'=>'required|exists:users,id','azure_id'=>'required|uuid']);
  try{$remote=$graph->get($data['azure_id']);}catch(Throwable $e){report($e);return back()->withErrors(['azure_id'=>'Unable to verify Azure identity.']);}
  $user=User::findOrFail($data['user_id']);abort_if($user->isPmo(),403);
  abort_if(($remote['accountEnabled']??true)===false,422,'Microsoft account is disabled.');
  $mail=strtolower(trim($remote['mail']?:($remote['userPrincipalName']??'')));
  if($mail!==strtolower($user->email))return back()->withErrors(['azure_id'=>'Azure email must match the existing account.']);
  $existing=SocialAccount::where('provider','azure')->where('tenant_id',config('services.azure.tenant'))->where('provider_user_id',$remote['id'])->first();
  if($existing && $existing->user_id!==$user->id)return back()->withErrors(['azure_id'=>'That Microsoft account is already linked to someone else.']);
  DB::transaction(function()use($user,$remote){SocialAccount::firstOrCreate(['provider'=>'azure','tenant_id'=>config('services.azure.tenant'),'provider_user_id'=>$remote['id']],['user_id'=>$user->id]);});
  return back()->with('success','Existing user linked to Microsoft.');
 }
}
