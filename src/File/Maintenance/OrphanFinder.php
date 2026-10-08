<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Maintenance;

use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\DeleteResult;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

/**
 * Files used nowhere (file reference registry).
 */
class OrphanFinder
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly FileReferenceRegistry $references,
        private readonly FileLibrary $library,
    ) {
    }

    /**
     * @param int|null $olderThan days: only the files created before (a file without date counts as old)
     *
     * @return list<FileInterface>
     */
    public function find(?int $olderThan = null): array
    {
        $used = array_fill_keys($this->references->usedIds(), true);
        $limit = null === $olderThan ? null : new \DateTimeImmutable('-' . $olderThan . ' days');

        return array_values(array_filter(
            $this->files->findBy([], ['path' => 'ASC']),
            static fn (FileInterface $file): bool => !isset($used[$file->getId()]) && (!$limit instanceof \DateTimeImmutable || !self::isNewer($file, $limit)),
        ));
    }

    /**
     * The library checks the usages again: a file used in between is kept.
     *
     * @param list<FileInterface> $files
     */
    public function delete(array $files): DeleteResult
    {
        return $this->library->delete($files);
    }

    private static function isNewer(FileInterface $file, \DateTimeImmutable $limit): bool
    {
        $createdAt = $file instanceof TimestampableInterface ? $file->getCreatedAt() : null;

        return $createdAt instanceof \DateTimeImmutable && $createdAt >= $limit;
    }
}
