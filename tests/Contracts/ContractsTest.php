<?php

declare(strict_types=1);

use Geoffrey\Contracts\Channel;

it('defines Channel contract with register method accepting name and config', function (): void {
    $reflection = new ReflectionClass(Channel::class);

    expect($reflection->isInterface())->toBeTrue();

    $method = $reflection->getMethod('register');

    expect($method)->not->toBeNull();

    $params = $method->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('name');
    expect($params[0]->getType()->getName())->toBe('string');
    expect($params[1]->getName())->toBe('config');
    expect($params[1]->getType()->getName())->toBe('array');

    expect($method->getReturnType()->getName())->toBe('void');
});

it('allows a class to implement Channel contract', function (): void {
    $channel = new class implements Channel
    {
        public function register(string $name, array $config): void {}
    };

    expect($channel)->toBeInstanceOf(Channel::class);
});
