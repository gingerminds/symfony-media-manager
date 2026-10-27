<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Serializes the operations that choose a free path (FileStorage::availablePath()) and then write it:
 * the path is picked, written and saved while its names are locked.
 */
final readonly class PathLocker
{
    private const string PREFIX = 'gingerminds_media_manager.path.';

    /**
     * Expiring stores (Redis, PDO...) only; long enough for a large upload to a remote disk.
     */
    private const float TTL = 300.0;

    public function __construct(
        private LockFactory $locks,
    ) {
    }

    /**
     * @template T
     *
     * @param list<array{string, string, string}> $targets   disk, directory on the disk, wanted file name
     * @param callable(): T                       $operation
     *
     * @return T
     */
    public function synchronized(array $targets, callable $operation): mixed
    {
        $resources = array_values(array_unique(array_map(fn (array $target): string => $this->resource(...$target), $targets)));
        // Same order everywhere: two batches cannot wait for each other.
        sort($resources);

        /** @var list<LockInterface> $acquired */
        $acquired = [];

        try {
            foreach ($resources as $resource) {
                $lock = $this->locks->createLock($resource, self::TTL);
                $lock->acquire(true);
                $acquired[] = $lock;
            }

            return $operation();
        } finally {
            foreach (array_reverse($acquired) as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * "photo.jpg", "photo-1.jpg", "Photo-1-2.JPG" share one resource: their suffixed candidates overlap.
     */
    public function resource(string $disk, string $directory, string $fileName): string
    {
        $name = (string) preg_replace('/(-\d+)+$/', '', pathinfo($fileName, \PATHINFO_FILENAME));
        $extension = pathinfo($fileName, \PATHINFO_EXTENSION);

        return self::PREFIX . hash('xxh128', $disk . "\0" . $directory . "\0" . strtolower($name . '.' . $extension));
    }
}
