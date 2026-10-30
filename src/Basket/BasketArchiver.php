<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket;

use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use League\Flysystem\FilesystemException;
use ZipStream\ZipStream;

/**
 * ZIP of the files of a basket, streamed: each file is read on its own disk and written to the
 * output, without temporary copy, under its original name ("name (2).pdf" for a duplicate).
 */
class BasketArchiver
{
    public function __construct(
        private readonly FileStorage $storage,
    ) {
    }

    /**
     * The files of the basket found on their disk, by entry name.
     *
     * @return array<string, FileInterface>
     */
    public function entries(BasketInterface $basket): array
    {
        $entries = [];
        $names = [];

        foreach ($basket->getMedias() as $media) {
            $file = $media->getFile();

            if ($file instanceof FileInterface && $this->storage->exists($file)) {
                $entries[$this->entryName($file->getOriginalName(), $names)] = $file;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, FileInterface> $entries
     * @param resource                     $output
     */
    public function write(array $entries, $output): void
    {
        $zip = new ZipStream(outputStream: $output, sendHttpHeaders: false);

        foreach ($entries as $name => $file) {
            try {
                $stream = $this->storage->readStream($file);
            } catch (FilesystemException) {
                // Deleted since entries(): left out.
                continue;
            }

            $zip->addFileFromStream($name, $stream);

            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        $zip->finish();
    }

    /**
     * @param array<string, true> $names the names already in the archive
     */
    private function entryName(string $originalName, array &$names): string
    {
        $name = $originalName;
        $base = pathinfo($originalName, \PATHINFO_FILENAME);
        $extension = pathinfo($originalName, \PATHINFO_EXTENSION);

        for ($index = 2; isset($names[mb_strtolower($name)]); ++$index) {
            $name = \sprintf('%s (%d)%s', $base, $index, '' === $extension ? '' : '.' . $extension);
        }

        $names[mb_strtolower($name)] = true;

        return $name;
    }
}
