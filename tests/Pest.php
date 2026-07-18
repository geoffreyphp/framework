<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

uses()
    ->beforeEach(function (): void {
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
    })
    ->in('Models');
