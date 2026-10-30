<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Controller;

use Gingerminds\MediaManagerBundle\Basket\BasketArchiver;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketVoter;
use Gingerminds\MediaManagerBundle\Http\FileResponseHeaders;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * `GET /api/baskets/{token}/download`: the files of the basket as a ZIP; the basket is kept, unless
 * `basket.delete_after_download`.
 */
final readonly class DownloadBasketController
{
    public function __construct(
        private BasketRepository $baskets,
        private BasketArchiver $archiver,
        private AuthorizationCheckerInterface $authorizationChecker,
        private bool $deleteAfterDownload = false,
    ) {
    }

    public function __invoke(string $token): BinaryFileResponse
    {
        $basket = $this->baskets->findOneByToken($token);

        if (!$basket instanceof BasketInterface) {
            throw new NotFoundHttpException('Basket not found.');
        }

        if (!$this->authorizationChecker->isGranted(BasketVoter::DOWNLOAD, $basket)) {
            throw new AccessDeniedHttpException('This basket belongs to another user.');
        }

        if ([] === $basket->getMedias()) {
            throw new UnprocessableEntityHttpException('The basket is empty.');
        }

        $archive = $this->archiver->archive($basket);

        if (null === $archive) {
            throw new UnprocessableEntityHttpException('No file of the basket could be found.');
        }

        if ($this->deleteAfterDownload) {
            $this->baskets->delete($basket);
        }

        $response = new BinaryFileResponse($archive, headers: ['Content-Type' => 'application/zip'])
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'basket.zip')
            ->deleteFileAfterSend();
        FileResponseHeaders::apply($response, 'application/zip');

        return $response;
    }
}
