<?php

declare(strict_types=1);

namespace G8Scope\CodeIgniter\Context;

/**
 * Builds the envelope context from the current CodeIgniter 4 request.
 *
 * To stay testable without booting the framework, the heavy lifting is done by
 * fromGlobals() which accepts plain $_SERVER-style data and a header map. The
 * capture() helper bridges a CI4 IncomingRequest (or any request-like object)
 * onto that, falling back to PHP superglobals when nothing is passed.
 */
final class RequestContext
{
    private const STRIP_HEADERS = ['authorization', 'cookie', 'set-cookie'];

    /**
     * Build context from an (optional) CI4-style request object.
     *
     * @param  object|null  $request  A CI4 IncomingRequest, or any object exposing
     *                                getMethod()/getHeaders(), or null to read globals.
     * @return array<string,mixed>
     */
    public static function capture(?object $request = null): array
    {
        try {
            if ($request === null) {
                return self::fromGlobals($_SERVER, self::headersFromGlobals($_SERVER));
            }

            $method = self::methodFromRequest($request);
            $url = self::urlFromRequest($request);
            $headers = self::headersFromRequest($request);

            if ($method === null && $url === null && $headers === []) {
                return self::fromGlobals($_SERVER, self::headersFromGlobals($_SERVER));
            }

            return self::assemble($method, $url, $headers);
        } catch (\Throwable) {
            return ['custom' => []];
        }
    }

    /**
     * Build context from plain values. The canonical, fully testable entry point.
     *
     * @param  array<string,mixed>  $server  $_SERVER-style array.
     * @param  array<string,mixed>  $headers  Raw header name => value map.
     * @return array<string,mixed>
     */
    public static function fromGlobals(array $server, array $headers): array
    {
        $method = isset($server['REQUEST_METHOD']) ? (string) $server['REQUEST_METHOD'] : null;
        $url = self::urlFromServer($server);

        if ($method === null && $url === null && $headers === []) {
            return ['custom' => []];
        }

        return self::assemble($method, $url, $headers);
    }

    /**
     * @param  array<string,mixed>  $headers
     * @return array<string,mixed>
     */
    private static function assemble(?string $method, ?string $url, array $headers): array
    {
        $request = ['custom' => []];

        $clean = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::STRIP_HEADERS, true)) {
                continue;
            }
            $clean[(string) $name] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        $request['request'] = [
            'method' => $method ?? '',
            'url' => $url ?? '',
            'headers' => $clean,
        ];

        return $request;
    }

    /** @param array<string,mixed> $server */
    private static function urlFromServer(array $server): ?string
    {
        if (! isset($server['HTTP_HOST']) && ! isset($server['SERVER_NAME'])) {
            return null;
        }

        $https = isset($server['HTTPS']) && $server['HTTPS'] !== '' && $server['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '');
        $uri = (string) ($server['REQUEST_URI'] ?? '');

        return $scheme.'://'.$host.$uri;
    }

    /**
     * @param  array<string,mixed>  $server
     * @return array<string,string>
     */
    private static function headersFromGlobals(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string) $key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    private static function methodFromRequest(object $request): ?string
    {
        foreach (['getMethod', 'method'] as $m) {
            if (method_exists($request, $m)) {
                $value = $request->{$m}();

                return $value !== null ? (string) $value : null;
            }
        }

        return null;
    }

    private static function urlFromRequest(object $request): ?string
    {
        // CI4 IncomingRequest exposes getUri() returning a URI stringable.
        if (method_exists($request, 'getUri')) {
            $uri = $request->getUri();

            return $uri !== null ? (string) $uri : null;
        }

        return null;
    }

    /** @return array<string,mixed> */
    private static function headersFromRequest(object $request): array
    {
        if (! method_exists($request, 'getHeaders')) {
            return [];
        }

        $headers = [];
        foreach ($request->getHeaders() as $name => $header) {
            // CI4 Header objects expose getValueLine(); fall back to string cast.
            if (is_object($header) && method_exists($header, 'getValueLine')) {
                $headers[(string) $name] = $header->getValueLine();
            } else {
                $headers[(string) $name] = is_array($header) ? implode(',', $header) : (string) $header;
            }
        }

        return $headers;
    }
}
