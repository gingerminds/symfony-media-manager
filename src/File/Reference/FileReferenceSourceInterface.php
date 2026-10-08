<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * Where files are referenced (associations, JSON fields...). Autoconfigured.
 */
interface FileReferenceSourceInterface
{
    public const string TAG = 'gingerminds_media_manager.file_reference_source';

    /**
     * @param list<string> $ids
     *
     * @return list<FileReference>
     */
    public function references(array $ids): array;

    /**
     * @return list<string> ids of the referenced files
     */
    public function usedIds(): array;

    /**
     * Points every reference to $old at $new, through the entities (cache invalidation).
     *
     * @return int number of updated entities
     */
    public function replace(FileInterface $old, FileInterface $new): int;
}
