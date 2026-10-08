<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsage;

final class FileInUseException extends \RuntimeException
{
    /**
     * @param list<FileUsage> $usages
     */
    public function __construct(
        public readonly FileInterface $usedFile,
        public readonly array $usages,
    ) {
        parent::__construct(\sprintf('The file "%s" is used by %d resource(s).', $usedFile->getOriginalName(), \count($usages)));
    }
}
