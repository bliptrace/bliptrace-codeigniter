<?php

declare(strict_types=1);

use G8Scope\CodeIgniter\Context\RequestContext;

it('builds method and url from globals', function (): void {
    $server = [
        'REQUEST_METHOD' => 'POST',
        'HTTP_HOST' => 'app.test',
        'REQUEST_URI' => '/orders/7?ref=x',
        'HTTPS' => 'on',
    ];

    $ctx = RequestContext::fromGlobals($server, ['Accept' => 'application/json']);

    expect($ctx['request']['method'])->toBe('POST')
        ->and($ctx['request']['url'])->toBe('https://app.test/orders/7?ref=x')
        ->and($ctx['request']['headers']['Accept'])->toBe('application/json')
        ->and($ctx['custom'])->toBe([]);
});

it('strips authorization and cookie headers', function (): void {
    $server = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'app.test', 'REQUEST_URI' => '/'];
    $headers = [
        'Authorization' => 'Bearer secret',
        'Cookie' => 'session=abc',
        'Set-Cookie' => 'session=def',
        'X-Request-Id' => '123',
    ];

    $ctx = RequestContext::fromGlobals($server, $headers);

    expect($ctx['request']['headers'])->not->toHaveKey('Authorization')
        ->and($ctx['request']['headers'])->not->toHaveKey('Cookie')
        ->and($ctx['request']['headers'])->not->toHaveKey('Set-Cookie')
        ->and($ctx['request']['headers']['X-Request-Id'])->toBe('123');
});

it('returns only custom when nothing is available', function (): void {
    expect(RequestContext::fromGlobals([], []))->toBe(['custom' => []]);
});

it('reads from a CI4-style request object', function (): void {
    $request = new class
    {
        public function getMethod(): string
        {
            return 'PUT';
        }

        public function getUri(): string
        {
            return 'https://api.test/v1/users/3';
        }

        /** @return array<string,object> */
        public function getHeaders(): array
        {
            $header = new class
            {
                public function getValueLine(): string
                {
                    return 'Bearer leak';
                }
            };

            return ['Authorization' => $header];
        }
    };

    $ctx = RequestContext::capture($request);

    expect($ctx['request']['method'])->toBe('PUT')
        ->and($ctx['request']['url'])->toBe('https://api.test/v1/users/3')
        ->and($ctx['request']['headers'])->not->toHaveKey('Authorization');
});

it('never throws on a broken request object', function (): void {
    $request = new class
    {
        public function getMethod(): string
        {
            throw new RuntimeException('broken');
        }
    };

    expect(fn () => RequestContext::capture($request))->not->toThrow(Throwable::class);
});
