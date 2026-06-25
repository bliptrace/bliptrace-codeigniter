<?php

declare(strict_types=1);

namespace G8Scope\CodeIgniter\Config;

use CodeIgniter\Config\BaseConfig;
use G8Scope\Php\Config as PhpConfig;

/**
 * CodeIgniter 4 settings holder for g8scope.
 *
 * In a CodeIgniter app this lives at app/Config/G8Scope.php and extends
 * CodeIgniter\Config\BaseConfig so that env() overrides apply automatically.
 * To keep the adapter testable without booting the framework, we only extend
 * the CI4 base class when it is actually available — otherwise this is a plain
 * settings object. Either way it maps to G8Scope\Php\Config via toPhpConfig().
 */
if (class_exists(BaseConfig::class)) {
    abstract class G8ScopeBase extends BaseConfig {}
} else {
    abstract class G8ScopeBase {}
}

class G8Scope extends G8ScopeBase
{
    /** Master kill switch — set false to disable all capture. */
    public bool $enabled = true;

    /** Project ingest key (from the g8scope dashboard). */
    public ?string $key = null;

    /** Ingest endpoint. The transport posts to {endpoint}/v1/ingest. */
    public string $endpoint = 'https://ingest.scope.g8suite.com';

    /** Deployment environment label. */
    public string $environment = 'production';

    /** Optional release / version identifier. */
    public ?string $release = null;

    /** HTTP timeout (seconds) for the send — kept short so a slow ingest never blocks the app. */
    public int $timeout = 3;

    /** Cap on stack frames sent. */
    public int $maxFrames = 30;

    /** Cap on exception message length sent. */
    public int $maxMessage = 2000;

    /**
     * Exception classes never reported (noise).
     *
     * @var array<int,string>
     */
    public array $ignoreExceptions = [];

    /**
     * Context keys redacted before sending (client-side scrub).
     *
     * @var array<int,string>
     */
    public array $scrubKeys = [
        'password', 'password_confirmation', 'token', 'secret', 'authorization',
        'api_key', 'apikey', 'access_token', 'refresh_token', 'cookie', 'set-cookie',
    ];

    /**
     * Absolute application base path used to flag in-app stack frames.
     * In a CI4 app, set this to ROOTPATH.
     */
    public string $basePath = '';

    public function __construct()
    {
        // Allow env() overrides when running inside CodeIgniter.
        if (function_exists('env')) {
            $this->enabled = (bool) env('g8scope.enabled', $this->enabled);
            $this->key = env('g8scope.key', $this->key) ?: null;
            $this->endpoint = (string) env('g8scope.endpoint', $this->endpoint);
            $this->environment = (string) env('g8scope.environment', $this->environment);
            $release = env('g8scope.release', $this->release);
            $this->release = $release !== null && $release !== '' ? (string) $release : null;
            $this->timeout = (int) env('g8scope.timeout', $this->timeout);
        }

        if (defined('ROOTPATH') && $this->basePath === '') {
            $this->basePath = ROOTPATH;
        }

        // Honour the parent constructor when running under CodeIgniter.
        if (is_callable([get_parent_class($this), '__construct'])) {
            parent::__construct();
        }
    }

    /** Map this settings holder to the runtime PHP SDK config. */
    public function toPhpConfig(): PhpConfig
    {
        return new PhpConfig(
            key: $this->key,
            endpoint: $this->endpoint,
            environment: $this->environment,
            release: $this->release,
            timeout: $this->timeout,
            maxFrames: $this->maxFrames,
            maxMessage: $this->maxMessage,
            ignoreExceptions: $this->ignoreExceptions,
            scrubKeys: $this->scrubKeys,
            basePath: $this->basePath,
            enabled: $this->enabled,
            sdkName: 'g8scope-codeigniter',
            sdkVersion: '1.0.0',
        );
    }
}
