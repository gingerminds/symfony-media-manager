<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\Media;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Controller\CrudContext;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Media\MediaPresenter;
use Gingerminds\MediaManagerBundle\Media\MediaUsageCounter;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deleting a media never deletes its files: they stay in the library.
 */
class MediaController extends AbstractCrudController
{
    public const int MAX_SEARCH_ITEMS = 100;

    private ?string $categoryId = null;

    public function __construct(
        CrudContext $context,
        protected readonly MediaCategoryRepository $categories,
        protected readonly MediaPresenter $presenter,
        protected readonly MediaUsageCounter $usages,
    ) {
        parent::__construct($context);
    }

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

    /**
     * The media picker modal of the MediaSelectType fields, loaded once per page.
     */
    public function picker(): Response
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, $this->getResource()->name);

        $categories = [];

        foreach ($this->categories->findFlatTree() as [$category, $depth]) {
            $ancestors = [];

            for ($parent = $category->getParent(); $parent instanceof MediaCategoryInterface; $parent = $parent->getParent()) {
                $ancestors[] = $parent->getId();
            }

            $categories[] = ['id' => $category->getId(), 'label' => str_repeat('— ', $depth) . $category, 'ancestors' => $ancestors];
        }

        return $this->render('@GingermindsMediaManager/components/media/_picker_modal.html.twig', ['categories' => $categories]);
    }

    /**
     * Medias of the picker: `search` (code, name), `category` and the allowed `categories[]`, their
     * subcategories included, `page`, `per_page`. Admin endpoint: the public API may be scoped by the project.
     */
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, $this->getResource()->name);
        $query = $request->query;
        $categoryIds = $this->categoryFilter($query->getInt('category'), array_values(array_map(intval(...), $query->all('categories'))));
        $page = $this->getRepository()->paginate(new ListQuery(
            page: max(1, $query->getInt('page', 1)),
            itemsPerPage: min(self::MAX_SEARCH_ITEMS, max(1, $query->getInt('per_page', 24))),
            sortBy: 'name',
            filters: array_filter([
                ListQuery::SEARCH_FILTER => $query->getString('search'),
                'category' => $categoryIds,
            ], static fn (mixed $value): bool => null !== $value && '' !== $value),
        ));

        return new JsonResponse([
            'items' => array_values(array_map(
                $this->presenter->media(...),
                array_filter($page->getItems(), static fn (object $media): bool => $media instanceof MediaInterface),
            )),
            'page' => $page->getPage(),
            'total' => $page->getTotalItems(),
            'hasMore' => $page->hasNextPage(),
        ]);
    }

    /**
     * @param list<int> $allowed
     *
     * @return list<int>|null null: no filter; [0]: nothing matches
     */
    private function categoryFilter(int $category, array $allowed): ?array
    {
        $allowed = [] === array_filter($allowed) ? null : $this->categories->findIdsWithDescendants($allowed);

        if (0 === $category) {
            return $allowed;
        }

        $ids = $this->categories->findIdsWithDescendants([$category]);

        if (null !== $allowed) {
            $ids = array_values(array_intersect($ids, $allowed));
        }

        return [] === $ids ? [0] : $ids;
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

    protected function getDeleteError(object $entity): ?string
    {
        $count = $entity instanceof MediaInterface ? $this->usages->count($entity) : 0;

        return $count > 0
            ? $this->trans('media.error.used', ['%count%' => $count], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN)
            : null;
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
