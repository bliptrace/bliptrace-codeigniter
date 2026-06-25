<?php

declare(strict_types=1);

namespace G8Scope\CodeIgniter;

use G8Scope\CodeIgniter\Context\RequestContext;
use G8Scope\Php\Client;
use Throwable;

/**
 * CodeIgniter reporter: enrich a Throwable with request context and ship it via
 * the runtime PHP client. Must NEVER throw into the host application.
 */
final class Reporter
{
    public function __construct(
        private readonly Client $client,
        private readonly ?object $request = null,
    ) {}

    /** Capture and ship an exception immediately (synchronously). */
    public function capture(Throwable $e): void
    {
        try {
            $envelope = $this->client->buildEnvelope($e, RequestContext::capture($this->request));
            if ($envelope === null) {
                return;
            }

            $this->client->sendEnvelope($envelope);
        } catch (Throwable) {
            // Never break the app.
        }
    }
}
