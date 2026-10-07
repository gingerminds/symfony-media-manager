<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores test files through the bundle (in-memory storage).
 */
final readonly class FileFactory
{
    public function __construct(
        private FileStorage $storage,
    ) {
    }

    public function text(string $originalName = 'notes.txt', string $content = 'content', string $directory = ''): FileInterface
    {
        return $this->storage->store($this->upload($originalName, $content), $directory);
    }

    public function png(string $originalName = 'photo.png', int $width = 200, int $height = 100): FileInterface
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return $this->text($originalName, (string) ob_get_clean());
    }

    public function svg(string $originalName = 'logo.svg'): FileInterface
    {
        return $this->text($originalName, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');
    }

    public function upload(string $originalName = 'notes.txt', string $content = 'content'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gm-upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, test: true);
    }
}
