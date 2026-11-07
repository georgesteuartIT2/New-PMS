<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
class UserDetailsController {
 public function show(User $user){
  $user->load('socialAccounts','memberships.role','memberships.project');
  return view('users.show',compact('user'));
 }
}
