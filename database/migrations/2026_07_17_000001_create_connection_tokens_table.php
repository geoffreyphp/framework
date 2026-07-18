<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: the (connection, user_id) unique index below does NOT enforce
     * uniqueness for shared tokens (user_id = null), because SQL treats
     * each NULL as distinct. Shared-token upsert uniqueness is enforced in
     * the token store layer via an explicit whereNull('user_id') lookup.
     */
    public function up(): void
    {
        Schema::create('connection_tokens', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('connection');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('token_type')->default('Bearer');
            $table->string('scope')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->timestamps();

            $table->unique(['connection', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_tokens');
    }
};
