<?php

declare(strict_types=1);

use Geoffrey\Channels\Slack\ProcessSlackEvent;
use Geoffrey\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\QueuedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

function makeSlackAgentResponse(string $text, ?string $conversationId = null): AgentResponse
{
    $usage = new Usage;
    $meta = new Meta;
    $response = new AgentResponse('invocation-id', $text, $usage, $meta);

    if ($conversationId !== null) {
        $response->withinConversation($conversationId);
    }

    return $response;
}

function makeSlackOrchestrator(AgentResponse $response): Agent
{
    return new class($response) implements Agent
    {
        public bool $forUserCalled = false;

        public bool $continueCalled = false;

        public ?string $continueConversationId = null;

        public ?object $continueUser = null;

        public ?object $forUserUser = null;

        public ?string $promptText = null;

        public function __construct(private readonly AgentResponse $agentResponse) {}

        public function forUser(object $user): static
        {
            $this->forUserCalled = true;
            $this->forUserUser = $user;

            return $this;
        }

        public function continue(string $conversationId, object $as): static
        {
            $this->continueCalled = true;
            $this->continueConversationId = $conversationId;
            $this->continueUser = $as;

            return $this;
        }

        public function instructions(): string
        {
            return '';
        }

        public function prompt(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
        {
            $this->promptText = $prompt;

            return $this->agentResponse;
        }

        public function stream(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): StreamableAgentResponse
        {
            throw new RuntimeException('Not implemented');
        }

        public function queue(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): QueuedAgentResponse
        {
            throw new RuntimeException('Not implemented');
        }

        public function broadcast(string $prompt, Channel|array $channels, array $attachments = [], bool $now = false, Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
        {
            throw new RuntimeException('Not implemented');
        }

        public function broadcastNow(string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
        {
            throw new RuntimeException('Not implemented');
        }

        public function broadcastOnQueue(string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): QueuedAgentResponse
        {
            throw new RuntimeException('Not implemented');
        }
    };
}

function makeSlackContainer(Agent $orchestrator, ?HttpFactory $http = null, string $token = 'xoxb-test-token'): Container
{
    $container = new Container;
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $config = new ConfigRepository([
        'geoffrey' => [
            'channels' => [
                'my-workspace' => ['token' => $token],
            ],
        ],
    ]);
    $container->instance('config', $config);

    $fakeHttp = $http ?? new HttpFactory;
    if (! $http instanceof Factory) {
        $fakeHttp->fake([
            'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => true]),
        ]);
    }
    $container->instance(HttpFactory::class, $fakeHttp);

    return $container;
}

beforeEach(function (): void {
    Capsule::schema()->dropAllTables();

    Capsule::schema()->create('users', function ($table): void {
        $table->bigIncrements('id');
        $table->string('channel');
        $table->string('channel_account_id');
        $table->string('external_id');
        $table->string('name')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->unique(['channel', 'channel_account_id', 'external_id']);
    });
});

it('resolves the user via findOrCreateByIdentity with slack channel and account name', function (): void {
    $agentResponse = makeSlackAgentResponse('Hello!', 'conv-123');
    $orchestrator = makeSlackOrchestrator($agentResponse);
    $container = makeSlackContainer($orchestrator);

    Container::setInstance($container);

    $event = [
        'user' => 'U12345',
        'text' => 'Hello bot',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $job = new ProcessSlackEvent('my-workspace', $event);
    $job->handle();

    $user = User::where('channel', 'slack')
        ->where('channel_account_id', 'my-workspace')
        ->where('external_id', 'U12345')
        ->first();

    expect($user)->not->toBeNull();
    expect($user->channel)->toBe('slack');
    expect($user->channel_account_id)->toBe('my-workspace');
    expect($user->external_id)->toBe('U12345');
});

it('calls orchestrator forUser when there is no thread', function (): void {
    $agentResponse = makeSlackAgentResponse('Hello!', 'conv-123');
    $orchestrator = makeSlackOrchestrator($agentResponse);
    $container = makeSlackContainer($orchestrator);

    Container::setInstance($container);

    $event = [
        'user' => 'U12345',
        'text' => 'Hello bot',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $job = new ProcessSlackEvent('my-workspace', $event);
    $job->handle();

    expect($orchestrator->forUserCalled)->toBeTrue();
    expect($orchestrator->continueCalled)->toBeFalse();
    expect($orchestrator->forUserUser)->toBeInstanceOf(User::class);
});

it('calls orchestrator continue with thread_ts as conversation id when in a thread', function (): void {
    $agentResponse = makeSlackAgentResponse('Continuing!', 'conv-thread');
    $orchestrator = makeSlackOrchestrator($agentResponse);
    $container = makeSlackContainer($orchestrator);

    Container::setInstance($container);

    $event = [
        'user' => 'U12345',
        'text' => 'Hello bot',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
        'thread_ts' => '1111111111.111111',
    ];

    $job = new ProcessSlackEvent('my-workspace', $event);
    $job->handle();

    expect($orchestrator->continueCalled)->toBeTrue();
    expect($orchestrator->forUserCalled)->toBeFalse();
    expect($orchestrator->continueConversationId)->toBe('1111111111.111111');
    expect($orchestrator->continueUser)->toBeInstanceOf(User::class);
});

it('strips the bot mention from the message text before prompting', function (): void {
    $agentResponse = makeSlackAgentResponse('Stripped!', 'conv-123');
    $orchestrator = makeSlackOrchestrator($agentResponse);
    $container = makeSlackContainer($orchestrator);

    Container::setInstance($container);

    $event = [
        'user' => 'U12345',
        'text' => '<@BOTID> Hello there',
        'channel' => 'C12345',
        'ts' => '1234567890.123456',
    ];

    $job = new ProcessSlackEvent('my-workspace', $event);
    $job->handle();

    expect($orchestrator->promptText)->toBe('Hello there');
});

it('posts the orchestrator response back to the slack channel', function (): void {
    $agentResponse = makeSlackAgentResponse('My response', 'conv-123');
    $orchestrator = makeSlackOrchestrator($agentResponse);

    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => true]),
    ]);

    $container = makeSlackContainer($orchestrator, $http);
    Container::setInstance($container);

    $event = [
        'user' => 'U12345',
        'text' => 'Hello bot',
        'channel' => 'C67890',
        'ts' => '1234567890.123456',
    ];

    $job = new ProcessSlackEvent('my-workspace', $event);
    $job->handle();

    $http->assertSent(function (HttpRequest $request): bool {
        $body = $request->data();

        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $body['channel'] === 'C67890'
            && $body['text'] === 'My response';
    });
});

it('replies in the thread using thread_ts or the original message ts', function (): void {
    $agentResponse = makeSlackAgentResponse('Thread reply!', 'conv-thread');
    $orchestrator = makeSlackOrchestrator($agentResponse);

    $http = new HttpFactory;
    $http->fake([
        'https://slack.com/api/chat.postMessage' => HttpFactory::response(['ok' => true]),
    ]);

    $container = makeSlackContainer($orchestrator, $http);
    Container::setInstance($container);

    $event = [
        'user' => 'U12345',
        'text' => 'Hello bot',
        'channel' => 'C67890',
        'ts' => '1234567890.123456',
        'thread_ts' => '1111111111.111111',
    ];

    $job = new ProcessSlackEvent('my-workspace', $event);
    $job->handle();

    $http->assertSent(function (HttpRequest $request): bool {
        $body = $request->data();

        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && isset($body['thread_ts'])
            && $body['thread_ts'] === '1111111111.111111';
    });
});

it('implements ShouldQueue interface', function (): void {
    expect(ProcessSlackEvent::class)->toImplement(ShouldQueue::class);
});
