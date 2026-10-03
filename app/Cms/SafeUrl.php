<?php

namespace App\Cms;

/**
 * URLs coming from forms, query strings or settings (docs/CMS.md §13 E22).
 *
 * - internal(): where to send the browser after an action ("redirect" fields, url.intended…) — a page
 *   of this site only, never an open redirect: "//evil.com", "/\evil.com", "\\evil.com", whitespace
 *   tricks and other schemes all give the fallback.
 * - external(): links to other sites (social networks): https only.
 * - link(): either (the announcement banner's optional link).
 */
final class SafeUrl
{
    /** Backslash, whitespace (Unicode included), a control or an invisible formatting character anywhere. */
    private const UNSAFE = '/[\\\\\s\x00-\x1F\x7F]|\p{Z}|\p{Cc}|\p{Cf}/u';

    /**
     * A path of this site — exactly one leading "/" (not followed by "/" or "\"), no backslash,
     * whitespace or control character — as an absolute URL; an absolute URL of this very site is
     * kept as is. Anything else gives $fallback.
     */
    public static function internal(?string $url, string $fallback): string
    {
        if ($url === null || $url === '' || self::unsafe($url)) {
            return $fallback;
        }

        if (str_starts_with($url, '/')) {
            return isset($url[1]) && ($url[1] === '/' || $url[1] === '\\') ? $fallback : url($url);
        }

        $root = rtrim(url('/'), '/');

        return $url === $root || str_starts_with($url, $root.'/') || str_starts_with($url, $root.'?') || str_starts_with($url, $root.'#')
            ? $url
            : $fallback;
    }

    /** An https URL of another site (valid, with a host, no credentials, backslash or whitespace), or null. */
    public static function external(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || self::unsafe($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    /** internal() or external(): a page of this site or an https link, else null. */
    public static function link(?string $url): ?string
    {
        $url = $url === null ? null : trim($url);
        $internal = self::internal($url, '');

        return $internal !== '' ? $internal : self::external($url);
    }

    private static function unsafe(string $url): bool
    {
        return ! mb_check_encoding($url, 'UTF-8') || preg_match(self::UNSAFE, $url) === 1;
    }
}
