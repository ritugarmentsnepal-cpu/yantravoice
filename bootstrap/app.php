<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Fix: macOS XAMPP's Apache runs as 'daemon' — set TMPDIR to a writable location.
// This block is harmless on Linux (the directory won't exist, so it's skipped).
if (is_dir('/Applications/XAMPP/xamppfiles/temp')) {
    putenv('TMPDIR=/Applications/XAMPP/xamppfiles/temp');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('ad-video:cleanup')->daily()->at('03:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Register admin middleware alias
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        
        $middleware->redirectUsersTo('/studio');

        // Enable session support on API routes for CSRF + auth
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
