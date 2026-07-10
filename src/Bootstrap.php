<?php

declare(strict_types=1);

namespace Geoffrey;

use Geoffrey\Http\AgentController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

class Bootstrap
{
    public static function create(string $basePath): Application
    {
        $storagePath = $basePath.'/.geoffrey';

        foreach (['bootstrap/cache', 'cache', 'logs', 'sessions', 'views'] as $dir) {
            if (! is_dir($storagePath.'/'.$dir)) {
                mkdir($storagePath.'/'.$dir, 0755, true);
            }
        }

        $app = Application::configure(basePath: $basePath)
            ->withRouting(using: function (): void {
                Route::post('/agent', [AgentController::class, 'handle']);
            })
            ->withMiddleware(function (Middleware $middleware): void {
                $middleware->validateCsrfTokens(except: ['agent', 'webhooks/*']);
            })
            ->withExceptions(function (Exceptions $exceptions): void {})
            ->create();

        $app->useStoragePath($storagePath);
        $app->useBootstrapPath($storagePath.'/bootstrap');

        return $app;
    }
}
