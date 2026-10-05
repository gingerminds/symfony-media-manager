<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Storage;

use Gingerminds\MediaManagerBundle\Exception\UnknownDiskException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Flysystem storage of each disk name.
 */
final readonly class DiskRegistry
{
    /**
     * @param ServiceLocator<FilesystemOperator> $storages
     */
    public function __construct(
        private ServiceLocator $storages,
        private string $defaultDisk,
    ) {
    }

    public function get(string $disk): FilesystemOperator
    {
        if (!$this->storages->has($disk)) {
            throw UnknownDiskException::named($disk, array_keys($this->storages->getProvidedServices()));
        }

        return $this->storages->get($disk);
    }

    public function has(string $disk): bool
    {
        return $this->storages->has($disk);
    }

    public function defaultDisk(): string
    {
        if (!$this->has($this->defaultDisk)) {
            throw UnknownDiskException::named($this->defaultDisk, array_keys($this->storages->getProvidedServices()));
        }

        return $this->defaultDisk;
    }
}
