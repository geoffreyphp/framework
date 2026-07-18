<?php

declare(strict_types=1);

namespace Geoffrey\Connections\Http\Middleware;

use Closure;
use Geoffrey\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateSignedConnectRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()->getName();

        if (str_ends_with($routeName, '.connect')) {
            abort_unless($request->hasValidSignature(), 403);

            $connection = $this->connectionName($routeName);

            $userId = $request->integer('user') ?: null;

            if ($userId !== null && User::query()->whereKey($userId)->exists()) {
                $request->session()->put(self::sessionKey($connection), $userId);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    public static function sessionKey(string $connection): string
    {
        return "geoffrey.connections.{$connection}.oauth_user_id";
    }

    private function connectionName(string $routeName): string
    {
        // Route names look like "mcp.oauth.{connection}.connect".
        $withoutPrefix = mb_substr($routeName, mb_strlen('mcp.oauth.'));

        return mb_substr($withoutPrefix, 0, mb_strrpos($withoutPrefix, '.') ?: mb_strlen($withoutPrefix));
    }
}
