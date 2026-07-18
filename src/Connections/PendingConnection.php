<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Geoffrey\Connections\Exceptions\NonOauthConnectUrlException;
use Geoffrey\Connections\Exceptions\NotConnectedException;
use Geoffrey\Connections\Exceptions\SharedConnectionUserBindingException;
use Geoffrey\Connections\Exceptions\UnboundConnectionUserException;
use Geoffrey\Models\User;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Facades\Mcp;

final readonly class PendingConnection
{
    public function __construct(
        private ConnectionDefinition $definition,
        private ConnectionManager $manager,
        private ConnectionTokenStore $tokenStore,
        private ConnectionContext $context,
        private ?User $user = null,
    ) {
        //
    }

    public function for(User $user): self
    {
        if ($this->definition->isShared()) {
            throw SharedConnectionUserBindingException::forName($this->definition->name);
        }

        return new self($this->definition, $this->manager, $this->tokenStore, $this->context, $user);
    }

    public function connectUrl(): string
    {
        if (! $this->definition->isOauth()) {
            throw NonOauthConnectUrlException::forName($this->definition->name);
        }

        return $this->manager->connectUrl($this->definition, $this->user);
    }

    public function connected(): bool
    {
        $this->ensureUserIsBoundWhenRequired();

        if (! $this->definition->isOauth()) {
            return true;
        }

        return $this->tokenStore->retrieve($this->definition, $this->user) !== null;
    }

    /** @return Collection<string, Tool> */
    public function tools(): Collection
    {
        if (! $this->connected()) {
            throw NotConnectedException::forName($this->definition->name, $this->connectUrl());
        }

        if ($this->user instanceof User) {
            $this->context->bind($this->definition->name, $this->user);
        }

        try {
            return Mcp::client($this->definition->name)->tools();
        } catch (AuthorizationRequiredException) {
            throw NotConnectedException::forName($this->definition->name, $this->connectUrl());
        } finally {
            $this->context->reset($this->definition->name);
        }
    }

    private function ensureUserIsBoundWhenRequired(): void
    {
        if (! $this->definition->isShared() && ! $this->user instanceof User) {
            throw UnboundConnectionUserException::forName($this->definition->name);
        }
    }
}
