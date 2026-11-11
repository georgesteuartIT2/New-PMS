<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsurePmo {
 public function handle(Request $request, Closure $next): Response {
  abort_unless($request->user()?->isPmo() || ($request->isMethodSafe() && $request->user()?->isAdminViewer()),403,'PMO access required.');
  return $next($request);
 }
}
