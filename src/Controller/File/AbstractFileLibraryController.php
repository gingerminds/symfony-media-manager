<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Controller\ResourceControllerTrait;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Shared by the library browser (reads) and the library actions (writes).
 */
abstract class AbstractFileLibraryController extends AbstractController
{
    use ResourceControllerTrait;

    public const string CSRF_TOKEN_ID = 'gm-file-library';

    protected const string RESOURCE = 'file';

    public function __construct(
        protected readonly FileLibrary $library,
        protected readonly FileRepository $files,
        protected readonly FileLibraryPresenter $presenter,
    ) {
    }

    /**
     * A library error (missing folder, upload refused...) translated.
     */
    protected function transException(TranslatableExceptionInterface $exception): string
    {
        $translator = $this->container->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return $exception->trans($translator);
    }

    /**
     * Unknown ids are ignored.
     *
     * @param array<mixed> $ids
     *
     * @return list<FileInterface>
     */
    protected function findFiles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (mixed $id): bool => \is_string($id) && Uuid::isValid($id))));

        return [] === $ids ? [] : array_values($this->files->findBy(['id' => $ids]));
    }

    /**
     * @return list<FileInterface>
     */
    protected function duplicatesOf(FileInterface $file): array
    {
        if (null === $file->getHash()) {
            return [];
        }

        return array_values(array_filter(
            $this->files->findBy(['hash' => $file->getHash()], ['createdAt' => 'ASC']),
            static fn (FileInterface $duplicate): bool => $duplicate->getId() !== $file->getId(),
        ));
    }
}
