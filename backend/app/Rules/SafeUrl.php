<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Allows only http(s) URLs, mailto: links, site-relative paths and #anchors.
 * Anything else (javascript:, data:, //host, backslashes, whitespace) is rejected.
 */
class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value)) {
            $fail('The :attribute must be an http(s) URL, a mailto: link, a /relative path or an #anchor.');
        }
    }

    public static function isSafe(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048) {
            return false;
        }

        // No whitespace, control, quote, angle-bracket or backslash characters anywhere.
        if (preg_match('/[\s\x00-\x1F\x7F<>"\'`\\\\]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//'); // protocol-relative URLs are off-site
        }

        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return (bool) preg_match('/^mailto:[^@\s]+@[^@\s]+$/i', $url);
    }
}
