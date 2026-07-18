<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Primary Agent Orchestrator
    |--------------------------------------------------------------------------
    |
    | This option controls who which agent is designated as the orchestrator
    | of the application. This agent will be the entry point for all incoming
    | requests and will be responsible for delegating tasks to other agents.
    |
    */

    'orchestrator' => Agents\Orchestrator::class,

    /*
    |--------------------------------------------------------------------------
    | Communication Channels
    |--------------------------------------------------------------------------
    |
    | Here you can configure the communications channels that users of the agent
    | will use to communicate with the agent.
    |
    | Available drivers: "slack"
    |
    */

    'channels' => [
        'slack' => [
            'driver' => 'slack',
            'token' => env('CHANNEL_SLACK_TOKEN'),
            'signing_secret' => env('CHANNEL_SLACK_SIGNING_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Here you can register the MCP connections available to your agents.
    | Each entry is the fully qualified class name of a connection class
    | annotated with the #[ConnectionName] and #[ConnectionUrl] attributes,
    | plus optionally #[WithOauth], #[WithToken], and #[Shared].
    |
    | - #[WithOauth] makes the connection go through the OAuth connect/
    |   callback flow; tokens are stored per user unless #[Shared] is
    |   also present, in which case a single app-level token is used.
    | - #[WithToken('ENV_KEY')] uses a static bearer token read from the
    |   named environment variable instead of OAuth. It cannot be combined
    |   with #[WithOauth].
    | - #[Shared] marks the connection as app-level rather than per-user.
    |   Shared connections cannot be bound to a user via ->for($user).
    |
    | Example (per-user OAuth connection):
    |
    | #[ConnectionName('clickup')]
    | #[ConnectionUrl('https://mcp.clickup.com/mcp')]
    | #[WithOauth]
    | class ClickUp
    | {
    |     //
    | }
    |
    | Example (shared static-token connection):
    |
    | #[ConnectionName('internal-docs')]
    | #[ConnectionUrl('https://mcp.internal.example.com/mcp')]
    | #[WithToken('INTERNAL_DOCS_MCP_TOKEN')]
    | #[Shared]
    | class InternalDocs
    | {
    |     //
    | }
    |
    | 'connections' => [
    |     Connections\ClickUp::class,
    |     Connections\InternalDocs::class,
    | ],
    |
    */

    'connections' => [],

    /*
    |--------------------------------------------------------------------------
    | Connect URL TTL
    |--------------------------------------------------------------------------
    |
    | The number of minutes a signed OAuth connect URL remains valid before
    | expiring. Connect URLs are handed to users in chat and must be visited
    | in a browser to complete the OAuth flow for a per-user connection.
    |
    */

    'connect_url_ttl' => 15,
];
