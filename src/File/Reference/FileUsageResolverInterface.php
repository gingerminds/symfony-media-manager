<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

/**
 * Turns a reference into the resource editing it. Autoconfigured, by priority: the first non null wins.
 */
interface FileUsageResolverInterface
{
    public const string TAG = 'gingerminds_media_manager.file_usage_resolver';

    public function resolve(FileReference $reference): ?FileUsage;
}
