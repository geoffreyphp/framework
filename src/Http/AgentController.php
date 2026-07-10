<?php

declare(strict_types=1);

namespace Geoffrey\Http;

use Geoffrey\Models\User;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Agent;
use RuntimeException;

class AgentController
{
    public function __construct(private readonly Container $container) {}

    /**
     * @param  array<string, array<string>>  $rules
     */
    private function validate(Request $request, array $rules): void
    {
        $factory = $this->container->make(ValidatorFactory::class);
        $validator = $factory->make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function handle(Request $request): JsonResponse
    {
        $this->validate($request, [
            'message' => ['required', 'string'],
            'conversation_id' => ['sometimes', 'string'],
        ]);

        $orchestrator = $this->container->make('geoffrey.orchestrator');

        if (! $orchestrator instanceof Agent) {
            throw new RuntimeException('No orchestrator is configured. Set geoffrey.orchestrator in your config.');
        }

        $user = User::findOrCreateByIdentity('agent', 'default', 'anonymous');

        $conversationId = $request->input('conversation_id');

        if (is_string($conversationId)) {
            $orchestrator->continue($conversationId, $user);
        } else {
            $orchestrator->forUser($user);
        }

        $message = $request->input('message');
        $response = $orchestrator->prompt(is_string($message) ? $message : '');

        return new JsonResponse([
            'message' => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }
}
