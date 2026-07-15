<?php

declare(strict_types=1);

use Geoffrey\Bootstrap;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

function cleanupBasePath(string $basePath): void
{
    array_map(unlink(...), glob($basePath.'/.geoffrey/**/*') ?: []);
    array_map(rmdir(...), glob($basePath.'/.geoffrey/*') ?: []);
    @rmdir($basePath.'/.geoffrey');
    @rmdir($basePath);
}

function makeBasePath(): string
{
    $basePath = sys_get_temp_dir().'/geoffrey-test-'.uniqid();
    mkdir($basePath, 0755, true);

    return $basePath;
}

it('creates a laravel application with the given base path', function (): void {
    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    expect($app)->toBeInstanceOf(Application::class);
    expect($app->basePath())->toBe($basePath);

    cleanupBasePath($basePath);
});

it('sets storage path to .geoffrey directory under base path', function (): void {
    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    expect($app->storagePath())->toBe($basePath.'/.geoffrey');

    cleanupBasePath($basePath);
});

it('disables CSRF for webhook paths', function (): void {
    PreventRequestForgery::flushState();

    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    // Resolving the HTTP kernel triggers the withMiddleware callback
    // which calls validateCsrfTokens and sets the static except list
    $app->make(Kernel::class);

    $reflection = new ReflectionClass(PreventRequestForgery::class);
    $prop = $reflection->getProperty('neverVerify');
    /** @var array<string> $neverVerify */
    $neverVerify = $prop->getValue();

    expect($neverVerify)->toContain('webhooks/*');
    expect($neverVerify)->not->toContain('agent');

    cleanupBasePath($basePath);
});

it('returns a configured Application instance', function (): void {
    $basePath = makeBasePath();

    $app = Bootstrap::create($basePath);

    expect($app)->toBeInstanceOf(Application::class);
    expect($app->basePath())->toBe($basePath);
    expect($app->storagePath())->toBe($basePath.'/.geoffrey');

    cleanupBasePath($basePath);
});
