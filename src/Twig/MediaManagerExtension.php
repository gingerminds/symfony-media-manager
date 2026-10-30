<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Twig;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig runtime (#[AsTwigFunction]): built on the first call of its functions.
 */
final readonly class MediaManagerExtension
{
    public const string FILE_ROUTE = 'gingerminds_media_manager_file';
    public const string FILE_PRESET_ROUTE = 'gingerminds_media_manager_file_preset';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Null without file, so templates can pass an optional relation.
     */
    #[AsTwigFunction('gm_file_url')]
    public function fileUrl(FileInterface|string|null $file, ?string $preset = null, bool $absolute = false): ?string
    {
        $id = $file instanceof FileInterface ? $file->getId() : $file;

        if (null === $id || '' === $id) {
            return null;
        }

        $referenceType = $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;

        return null === $preset
            ? $this->urlGenerator->generate(self::FILE_ROUTE, ['id' => $id], $referenceType)
            : $this->urlGenerator->generate(self::FILE_PRESET_ROUTE, ['id' => $id, 'preset' => $preset], $referenceType);
    }
}
