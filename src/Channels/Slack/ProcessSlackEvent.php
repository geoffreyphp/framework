<?php

declare(strict_types=1);

namespace Geoffrey\Channels\Slack;

use Geoffrey\Connections\Exceptions\NotConnectedException;
use Geoffrey\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Contracts\Agent;
use RuntimeException;

class ProcessSlackEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(
        public readonly string $channelAccountName,
        public readonly array $event,
    ) {}

    public function handle(): void
    {
        $token = config("geoffrey.channels.{$this->channelAccountName}.token");
        $orchestrator = app('geoffrey.orchestrator');

        if (! $orchestrator instanceof Agent) {
            throw new RuntimeException('No orchestrator is configured. Set geoffrey.orchestrator in your config.');
        }

        /** @var string $externalId */
        $externalId = $this->event['user'];

        /** @var string $channelId */
        $channelId = $this->event['channel'];

        /** @var string $ts */
        $ts = $this->event['ts'];

        $threadTs = isset($this->event['thread_ts']) && is_string($this->event['thread_ts'])
            ? $this->event['thread_ts']
            : null;

        /** @var string $rawText */
        $rawText = $this->event['text'];
        $text = trim((string) preg_replace('/<@[A-Z0-9]+>\s*/u', '', $rawText));

        $user = User::findOrCreateByIdentity('slack', $this->channelAccountName, $externalId);

        if ($threadTs !== null) {
            $orchestrator->continue($threadTs, $user);
        } else {
            $orchestrator->forUser($user);
        }

        try {
            $response = $orchestrator->prompt($text);
            $replyText = $response->text;
        } catch (NotConnectedException $e) {
            $replyText = "Before I can help with that, you need to connect your {$e->connection} account: <{$e->connectUrl}|Connect {$e->connection}>";
        }

        $replyTs = $threadTs ?? $ts;

        $tokenString = is_string($token) ? $token : '';

        $http = app(HttpFactory::class);
        $client = new SlackClient($tokenString, $http);
        $client->postMessage($channelId, $replyText, $replyTs);
    }
}
