<?php

declare(strict_types=1);
use Agents\Orchestrator;

it('loads the geoffrey config with default values', function (): void {
    $config = require __DIR__.'/../config/geoffrey.php';

    expect($config)->toBeArray();
});

it('has Agents\Orchestrator as the default orchestrator', function (): void {
    $config = require __DIR__.'/../config/geoffrey.php';

    expect($config['orchestrator'])->toBe(Orchestrator::class);
});

it('ships the slack channel example by default', function (): void {
    $config = require __DIR__.'/../config/geoffrey.php';

    expect($config['channels'])->toHaveKey('slack');
    expect($config['channels']['slack']['driver'])->toBe('slack');
});
