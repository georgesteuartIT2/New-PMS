<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsurePmo;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureActiveAccount;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['viewer.readonly'=>\App\Http\Middleware\EnsureViewerReadOnly::class,'pmo' => EnsurePmo::class, 'password.changed' => EnsurePasswordChanged::class, 'account.active' => EnsureActiveAccount::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void { })
    ->create();
