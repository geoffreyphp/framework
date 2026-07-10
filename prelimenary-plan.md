# Geoffrey v1 Implementation Plan — Framework Package

## Overview

This plan covers the **framework package only** (the `framework/` directory). The framework provides everything that's hidden from the user: bootstrap, routing, contracts, the base agent, first-party channels, the conversation/identity layer, and the service provider that wires it all together. The consuming application (skeleton or any host app) supplies values for the config the framework defines, and calls the framework's single public entry point, `Geoffrey\Bootstrap::create()`.

The framework's job: take a host app's root path + config (`orchestrator`, `channels`), stand up a Laravel application, register the agent endpoint and channel webhooks, and route inbound messages through the designated orchestrator while persisting conversation history.

---

## Boundary with the Consuming App

The framework owns everything below; the host app only provides:
- A call to `Geoffrey\Bootstrap::create($basePath)` from its front controller.
- Config values for `geoffrey.orchestrator` (an agent FQCN) and `geoffrey.channels` (driver entries).
- Agent/tool/skill classes (the framework resolves and runs them but does not define them).

Everything else in this document is internal to the framework package.

---

## Key Decisions (framework-relevant)

1. **Orchestrator resolved from config.** The framework reads `config('geoffrey.orchestrator')` (an agent FQCN) and binds it as the entry-point agent. Designation is config-driven (deployment-specific, Laravel-idiomatic, discoverable) rather than an attribute on the class.

2. **Channels are self-contained and driver-resolved.** The framework ships first-party channels under `Geoffrey\Channels\{Name}`, each implementing a `Channel` contract. They're selected via a driver pattern from config; the framework resolves the driver to its class and lets the channel register its own routes/controller.

3. **The channel owns the message lifecycle; the orchestrator is channel-agnostic.** The framework's channel controllers extract identity/metadata, call the orchestrator's process method, and route the structured response back. The orchestrator only sees a message + context.

4. **Provider/model is not proxied through Geoffrey config.** The framework does not own provider/model selection — the agent declares that via `laravel/ai`. The framework only needs API keys present in the environment.

5. **Conversation memory comes from `laravel/ai`'s `Conversational` trait.** The framework's responsibility is keying conversations correctly across channels and accounts, not building storage.

---

## File Structure (framework only)

```
framework/
    composer.json                          (deps + provider auto-discovery)
    config/
        geoffrey.php                       (config schema + defaults)
    database/
        migrations/
            2026_06_24_000001_create_agent_conversation_table.php
    src/
        Bootstrap.php                      (static create() — public entry point)
        GeoffreyServiceProvider.php        (service provider)
        Agent.php                          (base agent wrapping laravel/ai)
        Contracts/
            HasSkills.php                  (skills(): array)
            Channel.php                    (register(string $name, array $config): void)
        Channels/                          (first-party channels)
            Slack/
                SlackChannel.php           (implements Channel)
                SlackController.php
                ...                        (API client, request verification, etc.)
            Telegram/
                ...
        Http/
            AgentController.php            (generic POST /agent endpoint)
```

---

## Phase 1: Foundation

### Task 1.1 — `composer.json`

Dependencies + provider auto-discovery:

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "laravel/ai": "*"
    },
    "extra": {
        "laravel": {
            "providers": [
                "Geoffrey\\GeoffreyServiceProvider"
            ]
        }
    }
}
```

### Task 1.2 — `src/Bootstrap.php`

The framework's single public entry point. The host app passes its own root path (avoids basePath ambiguity). Storage is pointed at the host's `.geoffrey/` directory. Routing is defined inline — no `routes/` directory. The generic agent route is registered here; channel webhook routes are registered separately (Phase 6) by iterating configured channels.

```php
namespace Geoffrey;

class Bootstrap
{
    public static function create(string $basePath): Application
    {
        return Application::configure(basePath: $basePath)
            ->useStoragePath($basePath . '/.geoffrey')
            ->withRouting(
                using: function () {
                    Route::post('/agent', [AgentController::class, 'handle']);
                },
            )
            ->withMiddleware(function (Middleware $middleware): void {
                $middleware->validateCsrfTokens(except: ['agent', 'webhooks/*']);
            })
            ->withExceptions(function (Exceptions $exceptions): void {})
            ->create();
    }
}
```

Decisions:
- Single generic `POST /agent` endpoint for direct/testing use.
- Channel webhook routes registered via the `Channel` contract during boot.
- CSRF disabled for `agent` and channel webhook paths (APIs).
- Storage path set to the host's `.geoffrey/`; the provider ensures subdirectories exist.

---

## Phase 2: Contracts

### Task 2.1 — `src/Contracts/HasSkills.php`

Mirrors the AI SDK's `HasTools`. The framework resolves each name to `skills/{name}.md` relative to the host root.

```php
namespace Geoffrey\Contracts;

interface HasSkills
{
    /** @return array<string> Skill names that resolve to skills/{name}.md */
    public function skills(): array;
}
```

### Task 2.2 — `src/Contracts/Channel.php`

A channel wires up its own routes/controllers via `register()` (pattern inspired by Prism PHP's server). Keeps routing, controller, and API client together — no loose `routes/*.php` to discover.

```php
namespace Geoffrey\Contracts;

interface Channel
{
    /**
     * Register this channel's routes, controllers, and wiring.
     * Called once per configured channel during boot.
     *
     * @param string $name    The config key (e.g. 'slack_main')
     * @param array  $config  The driver-specific settings
     */
    public function register(string $name, array $config): void;
}
```

---

## Phase 3: Base Agent and Controller

### Task 3.1 — `src/Agent.php`

Base agent the host's agents extend:
- Implements the AI SDK's agent contracts.
- Loads its instructions (per-agent `instructions()` via the AI SDK contract).
- If the agent implements `HasSkills`, resolves names to `skills/{name}.md` (relative to the host root) and appends the markdown to instructions.
- If the agent implements `HasTools` (AI SDK) — including sub-agents registered as tools — those are available.
- Channel-agnostic: accepts a message + context, returns structured output.

### Task 3.2 — `src/Http/AgentController.php`

Generic `handle()` for direct/testing access (separate from channel webhooks):
- Validates `message` (required string) and `conversation_id` (optional string).
- Resolves the orchestrator binding (from `config('geoffrey.orchestrator')`).
- Calls the orchestrator's process method; returns JSON `{ message, conversation_id }`.

---

## Phase 4: First-Party Channels

### Task 4.1 — Channel implementations

First-party channels live under `Geoffrey\Channels\{Name}` (Slack, Telegram, …), each implementing `Channel`. Each bundles its API client, request verification, controller, and route registration. (The host app may add custom channels following the same contract; that's outside the framework package.)

### Task 4.2 — Driver resolution

Channels are selected by a driver pattern: a config entry's `driver` maps to a channel class; remaining keys are driver-specific settings. Multiple accounts of the same driver are supported. The framework resolves `driver` → channel class, instantiates it, and calls `register($name, $config)`.

> **Study reference:** Laravel's driver/manager pattern (`Illuminate\Support\Manager`, e.g. `CacheManager`) for resolving a class from config and instantiating with settings. Geoffrey's version is simpler — resolve, then call `register()`.

### Task 4.3 — Route registration

Inside `register()`, a channel defines its webhook routes with the `Route` facade, e.g.:
`Route::post("/webhooks/slack/{$name}", [SlackController::class, 'handle'])`.

---

## Phase 5: Message Flow & Identity (framework responsibilities)

### Task 5.1 — Inbound flow (channel-owned, framework-provided)

1. Webhook hits the channel's controller (framework).
2. Controller verifies the request (e.g. Slack signing secret).
3. Controller extracts: message text, `user_id`, `channel_account_id` (e.g. workspace ID), thread/conversation identifier.
4. Controller confirms which configured channel handled it (match against `geoffrey.channels`).
5. Controller resolves the orchestrator and calls its process method with message + context.
6. Orchestrator (agnostic) loads history, runs the turn, returns **structured output**.
7. Controller sends the structured response back via the channel's API client.

### Task 5.2 — Conversation identity

- Memory provided by `laravel/ai`'s `Conversational` trait.
- Conversations keyed by `channel` + `channel_account_id` + `user_id` (+ `conversation_id`/thread) so the same user ID across two accounts of the same driver never collides.

> **Open item:** Confirm how `Conversational` keys/stores conversations and how cleanly the framework can attach `channel` / `channel_account_id` / `user_id` (extra columns on the existing `agent_conversation` migration vs. a thin mapping). See Known Risks.

### Task 5.3 — Structured output

The orchestrator returns structured output by default (channels always parse and route it). Richer metadata in that structure is deferred — get a working string round-trip first, then expand once real needs surface.

---

## Phase 6: Service Provider

### Task 6.1 — `src/GeoffreyServiceProvider.php`

- **register():**
    - Merge framework `config/geoffrey.php` so host values override defaults.
    - Bind the orchestrator: resolve `config('geoffrey.orchestrator')`, load its instructions/skills.
    - Ensure `.geoffrey/` storage subdirectories exist (`cache/`, `sessions/`, `views/`, `logs/`).
- **boot():**
    - Load migrations from `database/migrations`.
    - Iterate `config('geoffrey.channels')`, resolve each `driver` to its channel class, instantiate, and call `register($name, $config)`.

---

## Config Schema (`framework/config/geoffrey.php`)

The framework defines the schema and safe defaults; the host app supplies values.

```php
return [
    // Agent FQCN designated as the entry-point/router. Supplied by the host app.
    'orchestrator' => null,

    // Named channel entries. Each has a 'driver' plus driver-specific settings.
    // Multiple accounts of the same driver are supported. Empty by default.
    'channels' => [
        // 'slack_main' => [
        //     'driver'         => 'slack',
        //     'workspace_id'   => env('SLACK_MAIN_WORKSPACE_ID'),
        //     'signing_secret' => env('SLACK_MAIN_SIGNING_SECRET'),
        //     'bot_token'      => env('SLACK_MAIN_BOT_TOKEN'),
        // ],
    ],
];
```

> Provider/model are intentionally absent — the agent declares those via `laravel/ai`; the framework only requires the relevant API keys in the environment.

---

## Phase 7: Verification (framework-focused)

Run the framework against a minimal host (test app or skeleton):

1. Require the package; confirm provider auto-discovery loads `GeoffreyServiceProvider`.
2. Confirm `Bootstrap::create($basePath)` boots, points storage at `.geoffrey/`, and creates the storage subdirectories.
3. `php artisan migrate` — confirm the conversation table loads from the package migrations.
4. Direct endpoint test:
   `curl -X POST http://localhost:8000/agent -H "Content-Type: application/json" -d '{"message": "Hello"}'`
   — confirm the configured orchestrator resolves, runs, and returns `{ message, conversation_id }`.
5. Channel test: configure one Slack entry; confirm `register()` is called during boot, the webhook route exists at `/webhooks/slack/{name}`, a round-trip works, and a follow-up persists history.
6. Confirm `Bootstrap`/provider resolve correctly under `artisan` as well as HTTP.

---

## Known Risks (framework)

1. **`laravel/ai` API surface** — Verify the actual contracts and methods assumed here: `HasTools`, `Conversational`, the agent's instructions/process/prompt methods, sub-agent-as-tool registration, and structured-output support. Most of Phases 3 and 5 depends on these matching.
2. **Conversation keying** — Confirm how `Conversational` stores/keys conversations and whether `channel` / `channel_account_id` / `user_id` can be attached cleanly (extra columns vs. mapping table). Handle gracefully when the DB isn't set up yet.
3. **Channel route registration timing** — Ensure each channel's `register()` runs at the right lifecycle point for both HTTP and `artisan`; verify the driver-resolution (Manager-style) approach.
4. **Entry-point resolution** — Confirm `\Geoffrey\Bootstrap` and the provider resolve for `artisan` commands as well as the host's front controller (autoloader ordering).
