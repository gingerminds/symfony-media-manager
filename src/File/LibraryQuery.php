<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

/**
 * Listing of the files of a library directory.
 */
final readonly class LibraryQuery
{
    /**
     * Mime type patterns (SQL LIKE) of each type filter.
     */
    public const array TYPES = [
        'image' => ['image/%'],
        'video' => ['video/%'],
        'audio' => ['audio/%'],
        'pdf' => ['application/pdf'],
        'document' => [
            'text/%', 'application/rtf',
            'application/doc', 'application/docx', 'application/dotx', 'application/odt',
            'application/xls', 'application/xlsx', 'application/xltx', 'application/ods',
            'application/ppt', 'application/pptx', 'application/potx', 'application/ppsx', 'application/odp',
        ],
        'archive' => ['application/zip', 'application/gzip', 'application/x-7z-compressed', 'application/x-rar%', 'application/x-tar'],
    ];

    public const array SORTS = ['name' => 'originalName', 'date' => 'createdAt', 'size' => 'size'];

    /**
     * @param string       $directory relative to the library root
     * @param bool         $recursive the subdirectories too (search across the library)
     * @param list<string> $accept    mime types of a file field (MimeTypePatterns), none: every type
     */
    public function __construct(
        public string $directory = '',
        public bool $recursive = false,
        public ?string $search = null,
        public ?string $type = null,
        public ?\DateTimeImmutable $createdFrom = null,
        public ?\DateTimeImmutable $createdTo = null,
        public bool $orphans = false,
        public bool $duplicates = false,
        public string $sortBy = 'name',
        public string $sort = 'asc',
        public int $page = 1,
        public ?int $itemsPerPage = null,
        public array $accept = [],
    ) {
    }

    /**
     * Types of the type filter that can hold an accepted mime type.
     *
     * @param list<string> $accept MimeTypePatterns, none: every type
     *
     * @return list<string>
     */
    public static function typesFor(array $accept): array
    {
        if ([] === $accept) {
            return array_keys(self::TYPES);
        }

        $overlaps = static function (string $a, string $b): bool {
            [$a, $aIsPrefix] = str_ends_with($a, '%') ? [substr($a, 0, -1), true] : [$a, false];
            [$b, $bIsPrefix] = str_ends_with($b, '%') ? [substr($b, 0, -1), true] : [$b, false];

            return ($aIsPrefix && str_starts_with($b, $a)) || ($bIsPrefix && str_starts_with($a, $b)) || $a === $b;
        };

        return array_keys(array_filter(self::TYPES, static fn (array $patterns): bool => array_any(
            $patterns,
            static fn (string $pattern): bool => array_any($accept, static fn (string $accepted): bool => $overlaps(MimeTypePatterns::toLike($accepted), $pattern)),
        )));
    }
}
