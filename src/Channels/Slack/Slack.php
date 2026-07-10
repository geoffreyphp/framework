<?php

declare(strict_types=1);

namespace Geoffrey\Channels\Slack;

use Geoffrey\Contracts\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

class Slack implements Channel
{
    /** @param array<string, mixed> $config */
    public function register(string $name, array $config): void
    {
        /** @var string $signingSecret */
        $signingSecret = $config['signing_secret'];

        /** @var Router $router */
        $router = app('router');

        $router->post("webhooks/{$name}/slack", fn (Request $request): JsonResponse|\Illuminate\Http\Response => new SlackController($name)->handle($request))->middleware(VerifySlackRequest::class.':'.$signingSecret);
    }
}
