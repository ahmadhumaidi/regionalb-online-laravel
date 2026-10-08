<?php

namespace App\Support;

class SocialPostUrl
{
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = mb_strtolower((string) $parts['host']);
        $host = preg_replace('/^(www|m|web)\./', '', $host) ?? $host;
        $path = rawurldecode((string) ($parts['path'] ?? ''));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = rtrim($path, '/');
        $path = $path === '' ? '/' : $path;

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query = array_intersect_key($query, array_flip(['id', 'fbid', 'story_fbid', 'v']));
        ksort($query);

        return $host.$path.($query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function fingerprint(?string $url): ?string
    {
        $normalized = self::normalize($url);

        return $normalized === null ? null : hash('sha256', $normalized);
    }
}
