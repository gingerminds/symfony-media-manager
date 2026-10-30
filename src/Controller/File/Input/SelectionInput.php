<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File\Input;

/**
 * Files (`ids`) and folders (`paths`) moved to `target` or deleted.
 */
final readonly class SelectionInput
{
    /**
     * @param list<string> $ids
     * @param list<string> $paths
     */
    public function __construct(
        public array $ids = [],
        public array $paths = [],
        public string $target = '',
    ) {
    }
}
