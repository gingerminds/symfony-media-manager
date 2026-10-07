<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\Media;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MediaCategoryController extends AbstractCrudController
{
    public const string REORDER_CSRF_TOKEN_ID = 'reorder-media-category';

    private ?string $parentId = null;

    protected function getResourceName(): string
    {
        return 'media_category';
    }

    /**
     * The whole tree, without pagination nor filters.
     */
    public function index(Request $request): Response
    {
        $resource = $this->getResource();
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, $resource->name);

        return $this->render($resource->template('index'), [
            ...$this->getCommonParameters(),
            'roots' => $this->getCategoryRepository()->findTree(),
            'reorder_csrf_token_id' => self::REORDER_CSRF_TOKEN_ID,
        ]);
    }

    /**
     * `?parent_id=` preselects the parent.
     */
    public function new(Request $request): Response
    {
        $this->parentId = $request->query->getString('parent_id') ?: null;

        return parent::new($request);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::EDIT, $this->getResource()->name);

        if (!$this->isCsrfTokenValid(self::REORDER_CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $payload = $request->getPayload();
        $parentId = $payload->get('parent_id');
        $ids = array_values(array_map(intval(...), array_filter($payload->all('ids'), is_numeric(...))));

        $this->getCategoryRepository()->reorder(is_numeric($parentId) ? (int) $parentId : null, $ids);

        return new JsonResponse(['success' => true]);
    }

    protected function createEntity(): object
    {
        $entity = parent::createEntity();

        if (null !== $this->parentId && $entity instanceof MediaCategoryInterface) {
            $parent = $this->getCategoryRepository()->find($this->parentId);
            $entity->setParent($parent instanceof MediaCategoryInterface ? $parent : null);
        }

        return $entity;
    }

    protected function getDeleteError(object $entity): ?string
    {
        return $entity instanceof MediaCategoryInterface && $entity->hasChildren()
            ? $this->trans('media_category.error.has_children', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN)
            : null;
    }

    protected function getCategoryRepository(): MediaCategoryRepository
    {
        $repository = $this->getRepository();

        if (!$repository instanceof MediaCategoryRepository) {
            throw new \LogicException(\sprintf('The repository of the media categories must extend "%s".', MediaCategoryRepository::class));
        }

        return $repository;
    }
}
