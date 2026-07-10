<?php

declare(strict_types=1);

namespace Geoffrey\Channels\Slack;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackRequest
{
    public function handle(Request $request, Closure $next, string $signingSecret): Response
    {
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! is_string($timestamp) || ! is_string($signature)) {
            return new HttpResponse('Forbidden', 403);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return new HttpResponse('Forbidden', 403);
        }

        $body = $request->getContent();
        $baseString = "v0:{$timestamp}:{$body}";
        $expected = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

        if (! hash_equals($expected, $signature)) {
            return new HttpResponse('Forbidden', 403);
        }

        $response = $next($request);

        return $response instanceof Response ? $response : new HttpResponse('', 200);
    }
}
