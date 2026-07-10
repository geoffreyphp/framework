<?php

declare(strict_types=1);

use Geoffrey\Channels\Slack\VerifySlackRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function makeSignedRequest(string $signingSecret, string $body = '{"event":"test"}', ?int $timestamp = null): Request
{
    $timestamp ??= time();
    $baseString = "v0:{$timestamp}:{$body}";
    $signature = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

    $request = Request::create('/slack/events', 'POST', [], [], [], [], $body);
    $request->headers->set('X-Slack-Request-Timestamp', (string) $timestamp);
    $request->headers->set('X-Slack-Signature', $signature);

    return $request;
}

it('passes the request through when the signature is valid', function (): void {
    $signingSecret = 'test-signing-secret';
    $request = makeSignedRequest($signingSecret);

    $middleware = new VerifySlackRequest;
    $next = fn (Request $req): Response => new Response('OK', 200);

    $response = $middleware->handle($request, $next, $signingSecret);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('OK');
});

it('rejects the request with 403 when the signature is missing', function (): void {
    $signingSecret = 'test-signing-secret';
    $request = makeSignedRequest($signingSecret);
    $request->headers->remove('X-Slack-Signature');

    $middleware = new VerifySlackRequest;
    $next = fn (Request $req): Response => new Response('OK', 200);

    $response = $middleware->handle($request, $next, $signingSecret);

    expect($response->getStatusCode())->toBe(403);
});

it('rejects the request with 403 when the timestamp header is missing', function (): void {
    $signingSecret = 'test-signing-secret';
    $request = makeSignedRequest($signingSecret);
    $request->headers->remove('X-Slack-Request-Timestamp');

    $middleware = new VerifySlackRequest;
    $next = fn (Request $req): Response => new Response('OK', 200);

    $response = $middleware->handle($request, $next, $signingSecret);

    expect($response->getStatusCode())->toBe(403);
});

it('rejects the request with 403 when the signature is invalid', function (): void {
    $signingSecret = 'test-signing-secret';
    $request = makeSignedRequest($signingSecret);
    $request->headers->set('X-Slack-Signature', 'v0=invalidsignature');

    $middleware = new VerifySlackRequest;
    $next = fn (Request $req): Response => new Response('OK', 200);

    $response = $middleware->handle($request, $next, $signingSecret);

    expect($response->getStatusCode())->toBe(403);
});

it('rejects the request with 403 when the timestamp is older than five minutes', function (): void {
    $signingSecret = 'test-signing-secret';
    $staleTimestamp = time() - 301;
    $request = makeSignedRequest($signingSecret, '{"event":"test"}', $staleTimestamp);

    $middleware = new VerifySlackRequest;
    $next = fn (Request $req): Response => new Response('OK', 200);

    $response = $middleware->handle($request, $next, $signingSecret);

    expect($response->getStatusCode())->toBe(403);
});
