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

    'orchestrator' => \Agents\Orchestrator::class,

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
];
