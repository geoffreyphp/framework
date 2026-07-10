<?php

declare(strict_types=1);

namespace Geoffrey\Channels\Slack;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class SlackClient
{
    public function __construct(
        private readonly string $botToken,
        private readonly HttpFactory $http,
    ) {}

    public function postMessage(string $channel, string $text, ?string $threadTs = null): void
    {
        $payload = [
            'channel' => $channel,
            'text' => $text,
        ];

        if ($threadTs !== null) {
            $payload['thread_ts'] = $threadTs;
        }

        $response = $this->http
            ->withToken($this->botToken)
            ->post('https://slack.com/api/chat.postMessage', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Slack API request failed with status: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (! isset($body['ok']) || $body['ok'] !== true) {
            $error = isset($body['error']) && is_string($body['error']) ? $body['error'] : 'unknown_error';
            throw new RuntimeException('Slack API returned an error: '.$error);
        }
    }
}
