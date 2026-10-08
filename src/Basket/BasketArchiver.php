<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket;

use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\ZipArchiveException;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use League\Flysystem\FilesystemException;

/**
 * ZIP of the files of a basket, each read on its own disk, under its original name ("name (2).pdf" for a duplicate).
 */
class BasketArchiver
{
    public function __construct(
        private readonly FileStorage $storage,
    ) {
    }

    /**
     * @return string|null the temporary ZIP file, null when no file could be read
     */
    public function archive(BasketInterface $basket): ?string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gm-basket');
        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw ZipArchiveException::couldNotCreate($path);
        }

        $names = [];
        $copies = [];

        try {
            foreach ($basket->getMedias() as $media) {
                $file = $media->getFile();
                $copy = $file instanceof FileInterface ? $this->copy($file) : null;

                if (null !== $copy) {
                    $copies[] = $copy;
                    $zip->addFile($copy, $this->entryName($file->getOriginalName(), $names));
                }
            }

            if ([] === $copies) {
                $zip->close();
                @unlink($path);

                return null;
            }

            if (!$zip->close()) {
                throw ZipArchiveException::couldNotClose($path);
            }
        } finally {
            array_map(static fn (string $copy): bool => @unlink($copy), $copies);
        }

        return $path;
    }

    /**
     * Local copy of the file (the archive reads its entries when closed), null when it is missing.
     */
    private function copy(FileInterface $file): ?string
    {
        try {
            $stream = $this->storage->readStream($file);
        } catch (FilesystemException) {
            return null;
        }

        $copy = (string) tempnam(sys_get_temp_dir(), 'gm-basket-file');
        $target = fopen($copy, 'w');

        if (false === $target) {
            return null;
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $copy;
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
