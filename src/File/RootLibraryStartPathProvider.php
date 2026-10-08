<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

final class RootLibraryStartPathProvider implements LibraryStartPathProviderInterface
{
    public function getStartPath(): string
    {
        return '';
    }
}
