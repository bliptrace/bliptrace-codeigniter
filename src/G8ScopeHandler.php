<?php

declare(strict_types=1);

namespace G8Scope\CodeIgniter;

use Config\G8Scope;
use G8Scope\CodeIgniter\Config\G8Scope as G8ScopeConfig;
use G8Scope\Php\Client;
use Throwable;

/**
 * Wiring point for CodeIgniter 4's exception handling.
 *
 * Wire it from app/Config/Events.php so it sees every uncaught throwable:
 *
 *   use G8Scope\CodeIgniter\G8ScopeHandler;
 *   Events::on('pre_system', static fn () => G8ScopeHandler::register());
 *
 * register() installs a PHP exception handler that reports, then re-delegates
 * to CI4's own handler so the framework still renders its error page. It never
 * throws into the host app.
 */
final class G8ScopeHandler
{
    private static ?Reporter $reporter = null;

    /** Build (and memoise) the reporter from config, returning null if unbuildable. */
    public static function reporter(?G8ScopeConfig $config = null, ?object $request = null): ?Reporter
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
     * Install a global exception handler that reports to g8scope, then chains to
     * the previous handler (CI4's) so normal error rendering is preserved.
     */
    public static function register(?G8ScopeConfig $config = null, ?object $request = null): void
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

    private static function resolveConfig(): ?G8ScopeConfig
    {
        // Prefer the application's own Config\G8Scope (loaded via CI4's config()).
        if (function_exists('config')) {
            $app = config('G8Scope');
            if ($app instanceof G8ScopeConfig) {
                return $app;
            }
        }

        if (class_exists(G8Scope::class)) {
            $app = new G8Scope;
            if ($app instanceof G8ScopeConfig) {
                return $app;
            }
        }

        return new G8ScopeConfig;
    }
}
