<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Doctrine\ORM\QueryBuilder;
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
     * Restricts a query of the files (root alias $alias) to those this source does not reference,
     * in the database as far as possible (`NOT EXISTS`).
     */
    public function excludeReferenced(QueryBuilder $qb, string $alias): void;

    /**
     * Points every reference to $old at $new, through the entities (cache invalidation).
     *
     * @return int number of updated entities
     */
    public function replace(FileInterface $old, FileInterface $new): int;
}
