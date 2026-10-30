<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\Media;

use Gingerminds\CoreBundle\Controller\AbstractTreeCrudController;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

class MediaCategoryController extends AbstractTreeCrudController
{
    protected function getDeleteError(object $entity): ?string
    {
        if (!$entity instanceof MediaCategoryInterface) {
            return null;
        }

        if ($entity->hasChildren()) {
            return $this->trans('media_category.error.has_children', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN);
        }

        $medias = $this->getDoctrine()->getRepository($this->getResourceRegistry()->getEntityClass('media'));

        return $medias instanceof MediaRepository && $medias->countByCategory($entity) > 0
            ? $this->trans('media_category.error.has_medias', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN)
            : null;
    }
}
