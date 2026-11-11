<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureViewerReadOnly {
 public function handle(Request $request,Closure $next){
  if($request->user()?->isAdminViewer()){
   $personal=$request->routeIs('logout','password.update','profile.update','profile.password','notifications.read','notifications.read-all','tasks.delays.read');
   abort_if(!$request->isMethodSafe() && !$personal,403,'Admin Viewer has read-only access.');
   abort_if($request->routeIs('projects.create','projects.edit','templates.create','templates.edit','settings.*','users.azure*','roles.*','role-directory.*','subsidiaries.*','administration.*'),403,'This management page is only available to PMO administrators.');
  }
  return $next($request);
 }
}
