<?php

declare(strict_types=1);

use Bliptrace\CodeIgniter\Reporter;
use Bliptrace\CodeIgniter\Tests\FakeTransport;
use Bliptrace\Php\Client;
use Bliptrace\Php\Config;

function reporterWith(FakeTransport $t, ?Config $config = null, ?object $request = null): Reporter
{
    $config ??= new Config(key: 'k', endpoint: 'http://api', basePath: '/srv/app', sdkName: 'bliptrace-codeigniter');

    return new Reporter(new Client($config, $t), $request);
}

it('builds and ships a valid envelope for a thrown exception', function (): void {
    $t = new FakeTransport;
    reporterWith($t)->capture(new RuntimeException('boom'));

    expect($t->sent)->toHaveCount(1);
    $env = $t->sent[0]['envelope'];

    expect($env['schema_version'])->toBe('1.1')
        ->and($env['event_id'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($env['runtime']['name'])->toBe('php')
        ->and($env['sdk']['name'])->toBe('bliptrace-codeigniter')
        ->and($env['sdk']['version'])->not->toBeEmpty()
        ->and($env['exception']['type'])->toBe('RuntimeException')
        ->and($env['exception']['message'])->toBe('boom')
        ->and($env['exception']['frames'][0])->toHaveKeys(['file', 'line', 'function', 'in_app']);
});

it('posts to the configured endpoint with the ingest key', function (): void {
    $t = new FakeTransport;
    reporterWith($t)->capture(new Exception('x'));

    expect($t->sent[0]['endpoint'])->toBe('http://api')
        ->and($t->sent[0]['key'])->toBe('k')
        ->and($t->sent[0]['envelope'])->not->toHaveKey('project'); // project resolved server-side from the key
});

it('skips ignored exception types', function (): void {
    $t = new FakeTransport;
    $config = new Config(key: 'k', ignoreExceptions: [LogicException::class]);
    reporterWith($t, $config)->capture(new LogicException('noise'));

    expect($t->sent)->toBeEmpty();
});

it('does nothing without a key or when disabled', function (): void {
    $t = new FakeTransport;
    reporterWith($t, new Config(key: null))->capture(new Exception('x'));
    reporterWith($t, new Config(key: 'k', enabled: false))->capture(new Exception('x'));

    expect($t->sent)->toBeEmpty();
});

it('never throws on weird input', function (): void {
    $t = new FakeTransport;
    $reporter = reporterWith($t);

    expect(fn () => $reporter->capture(new Exception('x')))->not->toThrow(Throwable::class)
        ->and(fn () => $reporter->capture(new Error('fatal-ish')))->not->toThrow(Throwable::class);
});
