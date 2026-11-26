<?php

declare(strict_types=1);

namespace Bliptrace\CodeIgniter;

use Config\Bliptrace;
use Bliptrace\CodeIgniter\Config\Bliptrace as BliptraceConfig;
use Bliptrace\Php\Client;
use Throwable;

/**
 * Wiring point for CodeIgniter 4's exception handling.
 *
 * Wire it from app/Config/Events.php so it sees every uncaught throwable:
 *
 *   use Bliptrace\CodeIgniter\BliptraceHandler;
 *   Events::on('pre_system', static fn () => BliptraceHandler::register());
 *
 * register() installs a PHP exception handler that reports, then re-delegates
 * to CI4's own handler so the framework still renders its error page. It never
 * throws into the host app.
 */
final class BliptraceHandler
{
    private static ?Reporter $reporter = null;

    /** Build (and memoise) the reporter from config, returning null if unbuildable. */
    public static function reporter(?BliptraceConfig $config = null, ?object $request = null): ?Reporter
    {
        if (self::$reporter !== null) {
            return self::$reporter;
        }

        try {
            $config ??= self::resolveConfig();
            if ($config === null) {
                return null;
            }

            $client = new Client($config->toPhpConfig());

            return self::$reporter = new Reporter($client, $request);
        } catch (Throwable) {
            return null;
        }
    }

    /** Report a single throwable. Safe to call from anywhere; never throws. */
    public static function report(Throwable $e, ?object $request = null): void
    {
        try {
            self::reporter(null, $request)?->capture($e);
        } catch (Throwable) {
            // Never break the app.
        }
    }

    /**
     * Install a global exception handler that reports to Bliptrace, then chains to
     * the previous handler (CI4's) so normal error rendering is preserved.
     */
    public static function register(?BliptraceConfig $config = null, ?object $request = null): void
    {
        try {
            self::reporter($config, $request);

            $previous = set_exception_handler(static function (Throwable $e) use (&$previous): void {
                self::report($e);

                if ($previous !== null) {
                    $previous($e);
                }
            });
        } catch (Throwable) {
            // Never break the app.
        }
    }

    /** Reset memoised state — primarily for tests. */
    public static function reset(): void
    {
        self::$reporter = null;
    }

    private static function resolveConfig(): ?BliptraceConfig
    {
        // Prefer the application's own Config\Bliptrace (loaded via CI4's config()).
        if (function_exists('config')) {
            $app = config('Bliptrace');
            if ($app instanceof BliptraceConfig) {
                return $app;
            }
        }

        if (class_exists(Bliptrace::class)) {
            $app = new Bliptrace;
            if ($app instanceof BliptraceConfig) {
                return $app;
            }
        }

        return new BliptraceConfig;
    }
}
