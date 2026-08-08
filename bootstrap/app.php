<?php

declare(strict_types=1);

use App\Exceptions\AturanAkademikException;
use App\Http\Middleware\EnsureBridgeScope;
use App\Http\Middleware\EnsureTermIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'term.active' => EnsureTermIsActive::class,
            'bridge.scope' => EnsureBridgeScope::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Guests land on the single sign-in page whichever portal they aimed at.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A refused academic rule is a normal outcome, not a crash: the person
        // who tripped it gets the reason back on the page they were already on.
        $exceptions->renderable(function (AturanAkademikException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('galat', $e->getMessage());
        });
    })->create();
