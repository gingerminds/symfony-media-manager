<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Security\Voter;

use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;

/**
 * `view|edit|delete media_categories` permissions.
 */
class MediaCategoryVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'media_category';
    }

    protected function getSubjectClass(): string
    {
        return MediaCategoryInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'media_categories';
    }
}
