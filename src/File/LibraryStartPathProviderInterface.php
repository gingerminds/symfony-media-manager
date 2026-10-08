<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

/**
 * Directory the library browser opens on, e.g. the folder of the current site.
 */
interface LibraryStartPathProviderInterface
{
    /**
     * @return string relative to the library root, "" for the root
     */
    public function getStartPath(): string;
}
