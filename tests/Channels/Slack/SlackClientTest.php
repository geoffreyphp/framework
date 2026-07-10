<?php

declare(strict_types=1);

use Geoffrey\Channels\Slack\SlackClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

it('posts a message to a slack channel', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => true]),
    ]);

    $client = new SlackClient('xoxb-test-token', $http);
    $client->postMessage('#general', 'Hello, world!');

    $http->assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $body['channel'] === '#general'
            && $body['text'] === 'Hello, world!';
    });
});

it('posts a message as a thread reply when thread_ts is provided', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => true]),
    ]);

    $client = new SlackClient('xoxb-test-token', $http);
    $client->postMessage('#general', 'Thread reply!', '1234567890.123456');

    $http->assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $body['channel'] === '#general'
            && $body['text'] === 'Thread reply!'
            && $body['thread_ts'] === '1234567890.123456';
    });
});

it('sends the bot token as a bearer authorization header', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => true]),
    ]);

    $client = new SlackClient('xoxb-test-token', $http);
    $client->postMessage('#general', 'Hello!');

    $http->assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer xoxb-test-token'));
});

it('throws a runtime exception when the slack api returns an error response', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response('Server Error', 500),
    ]);

    $client = new SlackClient('xoxb-test-token', $http);

    expect(fn () => $client->postMessage('#general', 'Hello!'))->toThrow(RuntimeException::class);
});

it('throws a runtime exception when the slack api returns ok false', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => false, 'error' => 'channel_not_found']),
    ]);

    $client = new SlackClient('xoxb-test-token', $http);

    expect(fn () => $client->postMessage('#general', 'Hello!'))->toThrow(RuntimeException::class);
});
