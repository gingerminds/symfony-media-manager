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
     * @param string $directory relative to the library root
     * @param bool   $recursive the subdirectories too (search across the library)
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
    ) {
    }
}
