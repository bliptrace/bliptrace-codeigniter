# Bliptrace — CodeIgniter 4 SDK

A thin CodeIgniter 4 adapter over the runtime PHP SDK
[`bliptrace/php`](../bliptrace-php). It auto-captures uncaught exceptions, enriches
them with request context, scrubs sensitive data, and ships an error envelope to
the Bliptrace ingest API.

It is built to **never throw into your application** — if Bliptrace is
misconfigured or the ingest endpoint is slow, your app keeps serving.

> The **project is never sent in the payload**. It is resolved server-side from
> your ingest key. Keep the key secret.

## Installation

```bash
composer require bliptrace/codeigniter
```

This pulls in `bliptrace/php` (the runtime). The `codeigniter4/framework` is a
peer of your application — this package only suggests it.

## Configuration

Create `app/Config/Bliptrace.php` extending the adapter config so CodeIgniter's
`config()` discovers it:

```php
<?php

namespace Config;

use Bliptrace\CodeIgniter\Config\Bliptrace as BaseConfig;

class Bliptrace extends BaseConfig
{
    public bool $enabled = true;

    public ?string $key = null; // overridden by env, see below

    public string $endpoint = 'https://ingest.bliptrace.com/v1/ingest';

    public string $environment = 'production';
}
```

Set values via `.env` (these override the matching properties at runtime):

```dotenv
bliptrace.enabled   = true
bliptrace.key       = your-project-ingest-key
bliptrace.endpoint  = https://ingest.bliptrace.com/v1/ingest
bliptrace.environment = production
bliptrace.release   = 1.4.2
bliptrace.timeout   = 3
```

`basePath` defaults to `ROOTPATH` inside CodeIgniter; it is used to flag in-app
stack frames.

## Wiring into CodeIgniter

### Option A — global handler via Events (recommended)

Register a global exception handler early in the boot. In
`app/Config/Events.php`:

```php
use CodeIgniter\Events\Events;
use Bliptrace\CodeIgniter\BliptraceHandler;

Events::on('pre_system', static function (): void {
    BliptraceHandler::register();
});
```

`register()` installs a `set_exception_handler` that reports to Bliptrace and then
chains to CodeIgniter's own handler, so the framework still renders its normal
error page.

### Option B — from the custom exception handler

If you use a custom handler in `app/Config/Exceptions.php`, call the reporter
explicitly:

```php
use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Bliptrace\CodeIgniter\BliptraceHandler;
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
        BliptraceHandler::report($exception, $request);

        // ... your rendering / parent handling
    }
}
```

## Manual capture

To report a caught exception yourself:

```php
use Bliptrace\CodeIgniter\BliptraceHandler;

try {
    risky();
} catch (\Throwable $e) {
    BliptraceHandler::report($e, service('request'));
    // ... handle gracefully
}
```

Or build a reporter directly:

```php
use Bliptrace\CodeIgniter\Reporter;
use Bliptrace\Php\Client;

$client   = new Client(config('Bliptrace')->toPhpConfig());
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

The test suite runs standalone against `bliptrace/php` and a `FakeTransport`; it
does **not** require booting CodeIgniter.

## Licence

MIT. See [LICENSE](LICENSE).
