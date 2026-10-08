<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Security\Voter;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * `view files`: browse and pick; `edit files`: every change, deletions included.
 */
class FileVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'file';
    }

    protected function getSubjectClass(): string
    {
        return FileInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'files';
    }

    protected function canDelete(UserInterface $user, ?object $subject): bool
    {
        return $this->canEdit($user, $subject);
    }
}
