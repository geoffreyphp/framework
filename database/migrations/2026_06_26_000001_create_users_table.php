<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('channel');
            $table->string('channel_account_id');
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'channel_account_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
