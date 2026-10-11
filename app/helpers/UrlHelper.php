<?php

if (!function_exists('app_is_loopback_host')) {
    function app_is_loopback_host(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        return $host === 'localhost'
            || $host === '::1'
            || $host === '127.0.0.1'
            || str_starts_with($host, '127.');
    }
}

if (!function_exists('app_detect_lan_ip')) {
    function app_detect_lan_ip(): ?string
    {
        $candidates = [];

        if (!empty($_SERVER['SERVER_ADDR'])) {
            $candidates[] = (string)$_SERVER['SERVER_ADDR'];
        }

        $hostname = gethostname();
        if ($hostname !== false) {
            $hostIp = gethostbyname($hostname);
            if ($hostIp !== $hostname) {
                $candidates[] = $hostIp;
            }
        }

        foreach ($candidates as $ip) {
            $ip = trim((string)$ip, '[]');
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !app_is_loopback_host($ip)) {
                return $ip;
            }
        }

        return null;
    }
}

if (!function_exists('app_public_base_url')) {
    function app_public_base_url(): string
    {
        $configuredBase = trim((string)(getenv('QR_PUBLIC_BASE_URL') ?: ''));
        if ($configuredBase !== '') {
            return rtrim($configuredBase, '/');
        }

        $rawHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $parsedHost = parse_url('http://' . $rawHost, PHP_URL_HOST);
        $parsedPort = parse_url('http://' . $rawHost, PHP_URL_PORT);

        $host = is_string($parsedHost) && $parsedHost !== '' ? $parsedHost : $rawHost;
        $port = is_int($parsedPort) ? ':' . $parsedPort : '';

        if (app_is_loopback_host($host)) {
            $host = app_detect_lan_ip() ?: $host;
        }

        return 'http://' . $host . $port;
    }
}

if (!function_exists('app_public_url')) {
    function app_public_url(string $path): string
    {
        return app_public_base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_trace_url')) {
    function app_trace_url(int $productId, ?string $batchCode = null, string $fragment = ''): string
    {
        $path = '/webbanhang/product/trace/' . $productId;
        if ($batchCode !== null && $batchCode !== '') {
            $path .= '?batch=' . rawurlencode($batchCode);
        }
        if ($fragment !== '') {
            $path .= '#' . ltrim($fragment, '#');
        }

        return app_public_url($path);
    }
}
