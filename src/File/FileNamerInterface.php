<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

/**
 * The names of the files and folders on the disk (the original name is kept apart). Alias it to
 * your service for another rule; the result is still confined to the library by PathGuard.
 */
interface FileNamerInterface
{
    /**
     * "Mon Fichier été.PDF" becomes "mon-fichier-ete.pdf" by default.
     */
    public function fileName(string $originalName): string;

    /**
     * "Actualités 2026" becomes "actualites-2026" by default; empty when nothing is left.
     */
    public function directoryName(string $name): string;
}
