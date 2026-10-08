<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Security\Voter;

use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

/**
 * `view|edit|delete medias` permissions.
 */
class MediaVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'media';
    }

    protected function getSubjectClass(): string
    {
        return MediaInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'medias';
    }
}
