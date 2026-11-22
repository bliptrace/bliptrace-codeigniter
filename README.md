# g8scope — CodeIgniter 4 SDK

A thin CodeIgniter 4 adapter over the runtime PHP SDK
[`g8scope/php`](../bliptrace-php). It auto-captures uncaught exceptions, enriches
them with request context, scrubs sensitive data, and ships an error envelope to
the g8scope ingest API.

It is built to **never throw into your application** — if g8scope is
misconfigured or the ingest endpoint is slow, your app keeps serving.

> The **project is never sent in the payload**. It is resolved server-side from
> your ingest key. Keep the key secret.

## Installation

```bash
composer require g8scope/codeigniter
```

This pulls in `g8scope/php` (the runtime). The `codeigniter4/framework` is a
peer of your application — this package only suggests it.

## Configuration

Create `app/Config/G8Scope.php` extending the adapter config so CodeIgniter's
`config()` discovers it:

```php
<?php

namespace Config;

use G8Scope\CodeIgniter\Config\G8Scope as BaseConfig;

class G8Scope extends BaseConfig
{
    public bool $enabled = true;

    public ?string $key = null; // overridden by env, see below

    public string $endpoint = 'https://ingest.scope.g8suite.com';

    public string $environment = 'production';
}
```

Set values via `.env` (these override the matching properties at runtime):

```dotenv
g8scope.enabled   = true
g8scope.key       = your-project-ingest-key
g8scope.endpoint  = https://ingest.scope.g8suite.com
g8scope.environment = production
g8scope.release   = 1.4.2
g8scope.timeout   = 3
```

`basePath` defaults to `ROOTPATH` inside CodeIgniter; it is used to flag in-app
stack frames.

## Wiring into CodeIgniter

### Option A — global handler via Events (recommended)

Register a global exception handler early in the boot. In
`app/Config/Events.php`:

```php
use CodeIgniter\Events\Events;
use G8Scope\CodeIgniter\G8ScopeHandler;

Events::on('pre_system', static function (): void {
    G8ScopeHandler::register();
});
```

`register()` installs a `set_exception_handler` that reports to g8scope and then
chains to CodeIgniter's own handler, so the framework still renders its normal
error page.

### Option B — from the custom exception handler

If you use a custom handler in `app/Config/Exceptions.php`, call the reporter
explicitly:

```php
use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use G8Scope\CodeIgniter\G8ScopeHandler;
use Throwable;

class MyExceptionHandler extends BaseExceptionHandler
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        G8ScopeHandler::report($exception, $request);

        // ... your rendering / parent handling
    }
}
```

## Manual capture

To report a caught exception yourself:

```php
use G8Scope\CodeIgniter\G8ScopeHandler;

try {
    risky();
} catch (\Throwable $e) {
    G8ScopeHandler::report($e, service('request'));
    // ... handle gracefully
}
```

Or build a reporter directly:

```php
use G8Scope\CodeIgniter\Reporter;
use G8Scope\Php\Client;

$client   = new Client(config('G8Scope')->toPhpConfig());
$reporter = new Reporter($client, service('request'));
$reporter->capture($e);
```

## Privacy / PII scrubbing

`authorization`, `cookie`, and `set-cookie` request headers are always
stripped. Any context keys listed in `scrubKeys` (passwords, tokens, secrets,
etc.) are redacted client-side **before** the envelope leaves your app.

## What gets sent

A language-neutral envelope: schema version, event id (idempotency key),
runtime (`php`), SDK name/version, environment/release, exception type, message,
normalised stack frames, and scrubbed request context. No `project` field —
that is resolved from the ingest key at the edge.

## Testing

```bash
composer install
composer test   # vendor/bin/pest
```

The test suite runs standalone against `g8scope/php` and a `FakeTransport`; it
does **not** require booting CodeIgniter.

## Licence

MIT. See [LICENSE](LICENSE).
