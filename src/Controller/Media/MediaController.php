<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\Media;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deleting a media never deletes its files: they stay in the library.
 */
class MediaController extends AbstractCrudController
{
    private ?string $categoryId = null;

    protected function getResourceName(): string
    {
        return 'media';
    }

    /**
     * `?category_id=` preselects the category.
     */
    public function new(Request $request): Response
    {
        $this->categoryId = $request->query->getString('category_id') ?: null;

        return parent::new($request);
    }

    protected function createEntity(): object
    {
        $entity = parent::createEntity();

        if (null !== $this->categoryId && $entity instanceof MediaInterface) {
            $category = $this->context->doctrine->getRepository($this->context->resources->getEntityClass('media_category'))->find($this->categoryId);
            $entity->setCategory($category instanceof MediaCategoryInterface ? $category : null);
        }

        return $entity;
    }

    protected function getCommonParameters(): array
    {
        $parameters = parent::getCommonParameters();

        if (isset($parameters['filter_configs']['category'])) {
            $parameters['filter_configs']['category']['entity'] = $this->context->resources->getEntityClass('media_category');
        }

        return $parameters;
    }
}
