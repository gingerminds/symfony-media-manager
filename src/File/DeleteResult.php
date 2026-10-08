<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsage;

final readonly class DeleteResult
{
    /**
     * @param list<FileInterface>                                       $deleted
     * @param list<array{file: FileInterface, usages: list<FileUsage>}> $blocked still used, kept
     */
    public function __construct(
        public array $deleted,
        public array $blocked,
    ) {
    }
}
