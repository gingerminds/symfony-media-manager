<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

/**
 * Accepted mime types of a file field: exact types ("application/pdf") or whole families ("image/*").
 */
final class MimeTypePatterns
{
    private const string PATTERN = '#^[a-z0-9][a-z0-9.+-]*/(\*|[a-z0-9][a-z0-9.+-]*)$#';

    /**
     * Invalid patterns are dropped (they come from the query string too).
     *
     * @param array<mixed> $patterns
     *
     * @return list<string>
     */
    public static function normalize(array $patterns): array
    {
        $patterns = array_map(static fn (mixed $pattern): string => \is_string($pattern) ? strtolower(trim($pattern)) : '', $patterns);

        return array_values(array_unique(array_filter($patterns, static fn (string $pattern): bool => 1 === preg_match(self::PATTERN, $pattern))));
    }

    /**
     * @param list<string> $patterns none: every type
     */
    public static function matches(string $mimeType, array $patterns): bool
    {
        if ([] === $patterns) {
            return true;
        }

        return array_any($patterns, static fn (string $pattern): bool => str_ends_with($pattern, '/*')
            ? str_starts_with(strtolower($mimeType), substr($pattern, 0, -1))
            : strtolower($mimeType) === $pattern);
    }

    /**
     * "image/*" becomes the SQL LIKE pattern "image/%".
     */
    public static function toLike(string $pattern): string
    {
        return str_ends_with($pattern, '/*') ? substr($pattern, 0, -1) . '%' : $pattern;
    }
}
