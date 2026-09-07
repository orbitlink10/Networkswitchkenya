<?php

namespace App\Support;

class CanonicalUrl
{
    /**
     * @param  array<string, mixed>  $query
     */
    public static function route(string $name, mixed $parameters = [], array $query = []): string
    {
        $url = route($name, $parameters);
        $query = array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '');

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return self::normalize($url);
    }

    public static function current(): string
    {
        return self::normalize(url()->current());
    }

    /**
     * Canonical URL for a category, with optional query string.
     *
     * @param  array<string, mixed>  $query
     */
    public static function category(\App\Models\Category $category, array $query = []): string
    {
        $url = \App\Support\SwitchCatalog::urlFor($category);
        $query = array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '');

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return self::normalize($url);
    }

    public static function absoluteAsset(string $url): string
    {
        $parts = parse_url($url);

        if ($parts !== false && isset($parts['host'])) {
            return $url;
        }

        return self::normalize($url);
    }

    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            $parts = parse_url(url($url)) ?: [];
        }

        $baseParts = parse_url((string) config('app.canonical_url')) ?: [];
        $scheme = $baseParts['scheme'] ?? $parts['scheme'] ?? request()->getScheme();
        $host = $baseParts['host'] ?? $parts['host'] ?? request()->getHost();
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';
        $path = '/'.ltrim($parts['path'] ?? '/', '/');
        $path = $path === '/' ? $path : rtrim($path, '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }
}
