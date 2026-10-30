<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Before a new content is stored in the library (an antivirus, a check of its own): throw
 * FileUploadException::rejected() to refuse it. A content already in the library is not stored again.
 */
final class FileUploadingEvent extends Event
{
    /**
     * @param string $directory relative to the library root
     */
    public function __construct(
        public readonly \SplFileInfo $file,
        public readonly string $directory,
    ) {
    }
}
