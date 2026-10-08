<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

/**
 * Also accepts the Laravel name of the category filter, `filters[media_category_id]`.
 *
 * @extends ResourceProvider<MediaInterface>
 */
class MediaProvider extends ResourceProvider
{
    public const string LEGACY_CATEGORY_FILTER = 'media_category_id';

    protected function configureListQuery(ListQuery $query, Operation $operation, array $uriVariables, array $context): ListQuery
    {
        if (!$query->hasFilter(self::LEGACY_CATEGORY_FILTER)) {
            return $query;
        }

        $value = $query->getFilter(self::LEGACY_CATEGORY_FILTER);

        return $query->withoutFilters(self::LEGACY_CATEGORY_FILTER)->withFilter('category', $query->getFilter('category', $value));
    }
}
