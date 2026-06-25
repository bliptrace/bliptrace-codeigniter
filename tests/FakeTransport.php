<?php

declare(strict_types=1);

namespace G8Scope\CodeIgniter\Tests;

use G8Scope\Php\Transport\Transport;

/** Records every envelope handed to it instead of sending it. */
final class FakeTransport implements Transport
{
    /** @var array<int,array<string,mixed>> */
    public array $sent = [];

    public function send(string $endpoint, string $key, array $envelope, int $timeout): void
    {
        $this->sent[] = compact('endpoint', 'key', 'envelope', 'timeout');
    }
}
