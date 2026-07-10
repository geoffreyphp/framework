<?php

declare(strict_types=1);

use Geoffrey\Channels\Slack\ProcessSlackEvent;
use Geoffrey\Channels\Slack\SlackController;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function makeControllerFakeDispatcher(): QueueingDispatcher
{
    return new class implements QueueingDispatcher
    {
        /** @var array<object> */
        public array $dispatched = [];

        public function dispatch(mixed $command): mixed
        {
            $this->dispatched[] = $command;

            return null;
        }

        public function dispatchSync(mixed $command, mixed $handler = null): mixed
        {
            return null;
        }

        public function dispatchNow(mixed $command, mixed $handler = null): mixed
        {
            return null;
        }

        public function dispatchAfterResponse(mixed $command, mixed $handler = null): void {}

        public function chain(mixed $jobs = null): mixed
        {
            return null;
        }

        public function hasCommandHandler(mixed $command): bool
        {
            return false;
        }

        public function getCommandHandler(mixed $command): mixed
        {
            return false;
        }

        /** @param array<string> $pipes */
        public function pipeThrough(array $pipes): static
        {
            return $this;
        }

        /** @param array<string, string> $map */
        public function map(array $map): static
        {
            return $this;
        }

        public function findBatch(string $batchId): mixed
        {
            return null;
        }

        public function batch(mixed $jobs): mixed
        {
            return null;
        }

        public function dispatchToQueue(mixed $command): mixed
        {
            $this->dispatched[] = $command;

            return null;
        }
    };
}

function makeSlackControllerContainer(?QueueingDispatcher $dispatcher = null): Container
{
    $fakeDispatcher = $dispatcher ?? makeControllerFakeDispatcher();

    $container = new Container;
    $container->instance(Dispatcher::class, $fakeDispatcher);
    $container->instance(QueueingDispatcher::class, $fakeDispatcher);

    Container::setInstance($container);

    return $container;
}

it('returns the challenge token for url verification requests', function (): void {
    makeSlackControllerContainer();

    $request = Request::create('/slack/events', 'POST', [
        'type' => 'url_verification',
        'challenge' => 'my-challenge-token',
    ]);

    $controller = new SlackController('my-workspace');
    $response = $controller->handle($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    $data = $response->getData(true);
    expect($data['challenge'])->toBe('my-challenge-token');
});

it('dispatches ProcessSlackEvent job with channel account name and event payload for app_mention events', function (): void {
    $dispatcher = makeControllerFakeDispatcher();
    makeSlackControllerContainer($dispatcher);

    $event = [
        'type' => 'app_mention',
        'user' => 'U12345',
        'text' => '<@BOT> Hello',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $request = Request::create('/slack/events', 'POST', [
        'type' => 'event_callback',
        'event' => $event,
    ]);

    $controller = new SlackController('my-workspace');
    $controller->handle($request);

    expect($dispatcher->dispatched)->toHaveCount(1);
    expect($dispatcher->dispatched[0])->toBeInstanceOf(ProcessSlackEvent::class);
    expect($dispatcher->dispatched[0]->channelAccountName)->toBe('my-workspace');
    expect($dispatcher->dispatched[0]->event)->toBe($event);
});

it('returns 200 with empty body for app_mention events', function (): void {
    makeSlackControllerContainer();

    $event = [
        'type' => 'app_mention',
        'user' => 'U12345',
        'text' => '<@BOT> Hello',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $request = Request::create('/slack/events', 'POST', [
        'type' => 'event_callback',
        'event' => $event,
    ]);

    $controller = new SlackController('my-workspace');
    $response = $controller->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('');
});

it('ignores event callbacks that are not app_mention type', function (): void {
    $dispatcher = makeControllerFakeDispatcher();
    makeSlackControllerContainer($dispatcher);

    $event = [
        'type' => 'message',
        'user' => 'U12345',
        'text' => 'Hello',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $request = Request::create('/slack/events', 'POST', [
        'type' => 'event_callback',
        'event' => $event,
    ]);

    $controller = new SlackController('my-workspace');
    $controller->handle($request);

    expect($dispatcher->dispatched)->toHaveCount(0);
});

it('returns 200 for ignored event types', function (): void {
    makeSlackControllerContainer();

    $event = [
        'type' => 'message',
        'user' => 'U12345',
        'text' => 'Hello',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $request = Request::create('/slack/events', 'POST', [
        'type' => 'event_callback',
        'event' => $event,
    ]);

    $controller = new SlackController('my-workspace');
    $response = $controller->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getStatusCode())->toBe(200);
});

it('ignores events that have a bot_id to prevent self-response loops', function (): void {
    $dispatcher = makeControllerFakeDispatcher();
    makeSlackControllerContainer($dispatcher);

    $event = [
        'type' => 'app_mention',
        'bot_id' => 'B12345',
        'user' => 'U12345',
        'text' => '<@BOT> Hello',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $request = Request::create('/slack/events', 'POST', [
        'type' => 'event_callback',
        'event' => $event,
    ]);

    $controller = new SlackController('my-workspace');
    $controller->handle($request);

    expect($dispatcher->dispatched)->toHaveCount(0);
});
