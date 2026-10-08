<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

final readonly class UploadResult
{
    /**
     * @param bool $duplicate the content was already in the library: $file is the existing one, nothing was stored
     */
    public function __construct(
        public FileInterface $file,
        public bool $duplicate = false,
    ) {
    }
}
