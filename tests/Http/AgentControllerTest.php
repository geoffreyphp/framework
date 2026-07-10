<?php

declare(strict_types=1);

use Geoffrey\Http\AgentController;
use Geoffrey\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\QueuedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

function makeContainer(): Container
{
    $container = new Container;
    $translator = new Translator(new ArrayLoader, 'en');
    $container->instance(ValidatorFactory::class, new ValidatorFactory($translator));

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

function makeAgentResponse(string $text, ?string $conversationId = null): AgentResponse
{
    $usage = new Usage;
    $meta = new Meta;
    $response = new AgentResponse('invocation-id', $text, $usage, $meta);

    if ($conversationId !== null) {
        $response->withinConversation($conversationId);
    }

    return $response;
}

function makeOrchestrator(AgentResponse $response): object
{
    return new class($response) implements Agent
    {
        private bool $forUserCalled = false;

        private bool $continueCalled = false;

        private ?string $continueConversationId = null;

        private ?object $continueUser = null;

        private ?object $forUserUser = null;

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

        public function wasForUserCalled(): bool
        {
            return $this->forUserCalled;
        }

        public function wasContinueCalled(): bool
        {
            return $this->continueCalled;
        }

        public function getContinueConversationId(): ?string
        {
            return $this->continueConversationId;
        }

        public function getContinueUser(): ?object
        {
            return $this->continueUser;
        }

        public function getForUserUser(): ?object
        {
            return $this->forUserUser;
        }
    };
}

it('validates that message is required', function (): void {
    $container = makeContainer();
    $request = Request::create('/agent', 'POST', []);

    $controller = new AgentController($container);

    expect(fn (): JsonResponse => $controller->handle($request))->toThrow(ValidationException::class);
});

it('validates that message is a string', function (): void {
    $container = makeContainer();
    $request = Request::create('/agent', 'POST', ['message' => ['not', 'a', 'string']]);

    $controller = new AgentController($container);

    expect(fn (): JsonResponse => $controller->handle($request))->toThrow(ValidationException::class);
});

it('accepts an optional conversation_id to continue a conversation', function (): void {
    $agentResponse = makeAgentResponse('Continuing!', 'conv-456');
    $orchestrator = makeOrchestrator($agentResponse);

    $container = makeContainer();
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $request = Request::create('/agent', 'POST', [
        'message' => 'Hello again',
        'conversation_id' => 'conv-456',
    ]);

    $controller = new AgentController($container);
    $response = $controller->handle($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    $data = $response->getData(true);
    expect($data['conversation_id'])->toBe('conv-456');
});

it('resolves the orchestrator from the container', function (): void {
    $agentResponse = makeAgentResponse('Resolved!', 'conv-789');
    $orchestrator = makeOrchestrator($agentResponse);

    $container = makeContainer();
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $request = Request::create('/agent', 'POST', ['message' => 'Test']);
    $controller = new AgentController($container);
    $response = $controller->handle($request);

    expect($response->getData(true)['message'])->toBe('Resolved!');
});

it('returns 422 when message is missing', function (): void {
    $container = makeContainer();
    $request = Request::create('/agent', 'POST', []);

    $controller = new AgentController($container);

    try {
        $controller->handle($request);
        expect(false)->toBeTrue('Should have thrown');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422);
        expect($e->errors())->toHaveKey('message');
    }
});

it('resolves or creates a user for the agent channel', function (): void {
    $agentResponse = makeAgentResponse('Hello!', 'conv-123');
    $orchestrator = makeOrchestrator($agentResponse);

    $container = makeContainer();
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $request = Request::create('/agent', 'POST', ['message' => 'Hello']);
    $controller = new AgentController($container);
    $controller->handle($request);

    $user = User::where('channel', 'agent')
        ->where('channel_account_id', 'default')
        ->where('external_id', 'anonymous')
        ->first();

    expect($user)->not->toBeNull();
    expect($user->channel)->toBe('agent');
    expect($user->channel_account_id)->toBe('default');
    expect($user->external_id)->toBe('anonymous');
});

it('calls forUser on orchestrator when no conversation_id provided', function (): void {
    $agentResponse = makeAgentResponse('Hello!', 'conv-123');
    $orchestrator = makeOrchestrator($agentResponse);

    $container = makeContainer();
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $request = Request::create('/agent', 'POST', ['message' => 'Hello']);
    $controller = new AgentController($container);
    $controller->handle($request);

    expect($orchestrator->wasForUserCalled())->toBeTrue();
    expect($orchestrator->wasContinueCalled())->toBeFalse();

    $forUserUser = $orchestrator->getForUserUser();
    expect($forUserUser)->toBeInstanceOf(User::class);
    expect($forUserUser->channel)->toBe('agent');
});

it('calls continue on orchestrator when conversation_id provided', function (): void {
    $agentResponse = makeAgentResponse('Continuing!', 'conv-456');
    $orchestrator = makeOrchestrator($agentResponse);

    $container = makeContainer();
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $request = Request::create('/agent', 'POST', [
        'message' => 'Continue here',
        'conversation_id' => 'conv-456',
    ]);

    $controller = new AgentController($container);
    $controller->handle($request);

    expect($orchestrator->wasContinueCalled())->toBeTrue();
    expect($orchestrator->wasForUserCalled())->toBeFalse();
    expect($orchestrator->getContinueConversationId())->toBe('conv-456');

    $continueUser = $orchestrator->getContinueUser();
    expect($continueUser)->toBeInstanceOf(User::class);
    expect($continueUser->channel)->toBe('agent');
});

it('returns a json response with message and conversation_id', function (): void {
    $agentResponse = makeAgentResponse('Hello!', 'conv-123');
    $orchestrator = makeOrchestrator($agentResponse);

    $container = makeContainer();
    $container->instance('geoffrey.orchestrator', $orchestrator);

    $request = Request::create('/agent', 'POST', ['message' => 'Hello']);

    $controller = new AgentController($container);
    $response = $controller->handle($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $data = $response->getData(true);

    expect($data)->toHaveKey('message');
    expect($data)->toHaveKey('conversation_id');
    expect($data['message'])->toBe('Hello!');
    expect($data['conversation_id'])->toBe('conv-123');
});
