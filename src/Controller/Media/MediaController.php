<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\Media;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Model\TreeNodes;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Media\MediaPresenter;
use Gingerminds\MediaManagerBundle\Media\MediaUsageCounter;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Deleting a media never deletes its files: they stay in the library.
 */
class MediaController extends AbstractCrudController
{
    public const int MAX_SEARCH_ITEMS = 100;

    public function __construct(
        protected readonly MediaCategoryRepository $categories,
        protected readonly MediaPresenter $presenter,
        protected readonly MediaUsageCounter $usages,
    ) {
    }

    /**
     * The media picker modal of the MediaSelectType fields, loaded once per page.
     */
    #[Route('/picker', name: 'picker', methods: ['GET'])]
    #[IsGranted(ResourceVoter::VIEW, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function picker(): Response
    {
        $categories = [];

        /** @var MediaCategoryInterface $category */
        foreach (TreeNodes::flatten($this->categories->findTreeNodes()) as [$category, $depth]) {
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
    #[Route('/search', name: 'search', methods: ['GET'])]
    #[IsGranted(ResourceVoter::VIEW, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query;
        $parameters = $this->getFilterParameters()->fromQuery($this->getResource()->entity, [
            'search' => $query->getString('search'),
            'category' => $this->categoryFilter($query->getInt('category'), array_values(array_map(intval(...), $query->all('categories')))),
            'order' => ['name' => 'asc'],
        ]);
        $page = $this->getRepository()->paginate(
            $parameters,
            max(1, $query->getInt('page', 1)),
            min(self::MAX_SEARCH_ITEMS, max(1, $query->getInt('per_page', 24))),
        );

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
        $allowed = [] === array_filter($allowed) ? null : array_map(intval(...), $this->categories->findIdsWithDescendants($allowed));

        if (0 === $category) {
            return $allowed;
        }

        $ids = array_map(intval(...), $this->categories->findIdsWithDescendants([$category]));

        if (null !== $allowed) {
            $ids = array_values(array_intersect($ids, $allowed));
        }

        return [] === $ids ? [0] : $ids;
    }

    /**
     * `?category_id=` preselects the category.
     */
    protected function createEntity(): object
    {
        $entity = parent::createEntity();
        $requestStack = $this->container->get('request_stack');
        \assert($requestStack instanceof RequestStack);
        $categoryId = $requestStack->getCurrentRequest()?->query->getString('category_id');

        if (null !== $categoryId && '' !== $categoryId && $entity instanceof MediaInterface) {
            $category = $this->categories->find($categoryId);
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
}
