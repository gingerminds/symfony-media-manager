<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Serializes the library writes:
 *
 * - the operations that choose a free path (FileStorage::availablePath()) and then write it: the
 *   path is picked, written and saved while its names are locked;
 * - the directory moves (DirectoryMover): a moved directory is locked exclusively, the directories
 *   written into (and their parents) are shared, so nothing lands in a directory being moved;
 * - the uploads of a content (content()): one row per content.
 *
 * Every lock of an operation is taken in the same order: two operations cannot wait for each other.
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
     * @param list<array{string, string}>         $moving    disk, directory on the disk moved by the operation
     *
     * @return T
     */
    public function synchronized(array $targets, callable $operation, array $moving = []): mixed
    {
        // Resource => exclusive.
        $resources = [];

        foreach ($targets as [$disk, $directory, $fileName]) {
            $resources[$this->resource($disk, $directory, $fileName)] = true;

            foreach ($this->directoryChain($directory) as $parent) {
                $resources[$this->directoryResource($disk, $parent)] ??= false;
            }
        }

        foreach ($moving as [$disk, $directory]) {
            foreach ($this->directoryChain($directory) as $parent) {
                $resources[$this->directoryResource($disk, $parent)] ??= false;
            }

            $resources[$this->directoryResource($disk, $directory)] = true;
        }

        return $this->acquire($resources, $operation);
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function content(string $hash, callable $operation): mixed
    {
        return $this->acquire([self::PREFIX . 'content.' . $hash => true], $operation);
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

    public function directoryResource(string $disk, string $directory): string
    {
        return self::PREFIX . 'dir.' . hash('xxh128', $disk . "\0" . trim($directory, '/'));
    }

    /**
     * "library/docs/old": "library", "library/docs", "library/docs/old".
     *
     * @return list<string>
     */
    private function directoryChain(string $directory): array
    {
        $chain = [];
        $current = '';

        foreach (array_filter(explode('/', $directory), static fn (string $segment): bool => '' !== $segment) as $segment) {
            $chain[] = $current = ('' === $current ? '' : $current . '/') . $segment;
        }

        return $chain;
    }

    /**
     * @template T
     *
     * @param array<string, bool> $resources resource => exclusive
     * @param callable(): T       $operation
     *
     * @return T
     */
    private function acquire(array $resources, callable $operation): mixed
    {
        ksort($resources);

        /** @var list<SharedLockInterface> $acquired */
        $acquired = [];

        try {
            foreach ($resources as $resource => $exclusive) {
                $lock = $this->locks->createLock((string) $resource, self::TTL);
                $exclusive ? $lock->acquire(true) : $lock->acquireRead(true);
                $acquired[] = $lock;
            }

            return $operation();
        } finally {
            foreach (array_reverse($acquired) as $lock) {
                $lock->release();
            }
        }
    }
}
