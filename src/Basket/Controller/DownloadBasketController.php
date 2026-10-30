<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Controller;

use Gingerminds\MediaManagerBundle\Basket\BasketArchiver;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketVoter;
use Gingerminds\MediaManagerBundle\Http\FileResponseHeaders;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * `GET /api/baskets/{token}/download`: the files of the basket as a streamed ZIP; the basket is kept, unless
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

    public function __invoke(string $token): StreamedResponse
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

        $entries = $this->archiver->entries($basket);

        if ([] === $entries) {
            throw new UnprocessableEntityHttpException('No file of the basket could be found.');
        }

        if ($this->deleteAfterDownload) {
            $this->baskets->delete($basket);
        }

        $response = new StreamedResponse(function () use ($entries): void {
            $output = fopen('php://output', 'w');

            if (false !== $output) {
                $this->archiver->write($entries, $output);
                fclose($output);
            }
        }, headers: [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'basket.zip'),
        ]);
        FileResponseHeaders::apply($response, 'application/zip');

        return $response;
    }
}
