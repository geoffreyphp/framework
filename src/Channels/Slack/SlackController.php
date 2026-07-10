<?php

declare(strict_types=1);

namespace Geoffrey\Channels\Slack;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SlackController
{
    public function __construct(private readonly string $channelAccountName) {}

    public function handle(Request $request): JsonResponse|Response
    {
        $type = $request->input('type');

        if ($type === 'url_verification') {
            return new JsonResponse(['challenge' => $request->input('challenge')]);
        }

        if ($type === 'event_callback') {
            /** @var array<string, mixed> $event */
            $event = $request->input('event', []);
            $eventType = isset($event['type']) && is_string($event['type']) ? $event['type'] : '';
            $hasBotId = isset($event['bot_id']);

            if ($eventType === 'app_mention' && ! $hasBotId) {
                $dispatcher = app(Dispatcher::class);
                $dispatcher->dispatch(new ProcessSlackEvent($this->channelAccountName, $event));
            }
        }

        return new Response('', 200);
    }
}
