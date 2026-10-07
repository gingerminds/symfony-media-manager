<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Default resolver: the owner itself, labelled and linked through its `gingerminds_core` resource.
 */
final readonly class ResourceFileUsageResolver implements FileUsageResolverInterface
{
    public function __construct(
        private ResourceRegistry $resources,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function resolve(FileReference $reference): FileUsage
    {
        $owner = $reference->owner;
        $id = $owner instanceof ResourceInterface ? $owner->getId() : null;
        $title = $owner instanceof \Stringable && '' !== (string) $owner ? (string) $owner : '#' . $id;
        $resource = $this->resources->findByEntity($owner);

        if (!$resource instanceof ResourceDefinition) {
            return new FileUsage(new \ReflectionClass($owner)->getShortName(), $title);
        }

        $editUrl = null;

        if (null !== $resource->controller && null !== $id) {
            try {
                $editUrl = $this->urlGenerator->generate($resource->route('edit'), ['id' => $id]);
            } catch (RouteNotFoundException) {
            }
        }

        return new FileUsage($this->translator->trans($resource->translationKey('name_s'), [], $resource->translationDomain), $title, $editUrl);
    }
}
