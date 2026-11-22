<?php

declare(strict_types=1);

use G8Scope\CodeIgniter\Config\G8Scope;

it('maps the settings holder to a runtime php config', function (): void {
    $config = new G8Scope;
    $config->key = 'abc';
    $config->endpoint = 'http://api';
    $config->environment = 'staging';
    $config->basePath = '/srv/app';

    $php = $config->toPhpConfig();

    expect($php->key)->toBe('abc')
        ->and($php->endpoint)->toBe('http://api')
        ->and($php->environment)->toBe('staging')
        ->and($php->basePath)->toBe('/srv/app')
        ->and($php->sdkName)->toBe('g8scope-codeigniter')
        ->and($php->enabled)->toBeTrue();
});

it('carries scrub keys and ignore lists through to the php config', function (): void {
    $config = new G8Scope;
    $config->ignoreExceptions = [LogicException::class];
    $config->scrubKeys = ['password'];

    $php = $config->toPhpConfig();

    expect($php->ignoreExceptions)->toBe([LogicException::class])
        ->and($php->scrubKeys)->toBe(['password']);
});

// Stand-in for CodeIgniter's env() helper (reads $_ENV) so the override path runs.
if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

it('reads bliptrace.* env, falls back to g8scope.*, then defaults', function (): void {
    $_ENV['bliptrace.key'] = 'new-key';
    $_ENV['g8scope.key'] = 'old-key';
    $_ENV['g8scope.endpoint'] = 'http://old';

    $config = new G8Scope;
    unset($_ENV['bliptrace.key'], $_ENV['g8scope.key'], $_ENV['g8scope.endpoint']);

    expect($config->key)->toBe('new-key')
        ->and($config->endpoint)->toBe('http://old')
        ->and($config->environment)->toBe('production')
        ->and($config->timeout)->toBe(3);
});
