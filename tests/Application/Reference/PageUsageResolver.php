<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Reference;

use Gingerminds\MediaManagerBundle\File\Reference\FileReference;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsage;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsageResolverInterface;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Page;

/**
 * A project resolver for an entity without admin resource.
 */
final class PageUsageResolver implements FileUsageResolverInterface
{
    public function resolve(FileReference $reference): ?FileUsage
    {
        return $reference->owner instanceof Page ? new FileUsage('Page', $reference->owner->title, '/pages/' . $reference->owner->getId()) : null;
    }
}
