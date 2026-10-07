<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Menu;

use Gingerminds\CoreBundle\Menu\AdminMenuProviderInterface;
use Gingerminds\CoreBundle\Menu\MenuItem;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;

/**
 * "Media library" section of the admin menu.
 */
final readonly class MediaManagerAdminMenuProvider implements AdminMenuProviderInterface
{
    public const string SECTION = 'media_manager';

    private const array ENTRIES = [
        'media_category' => ['bi-diagram-3', 30],
    ];

    public function __construct(
        private ResourceRegistry $resources,
    ) {
    }

    public function getItems(): iterable
    {
        $children = [];

        foreach (self::ENTRIES as $name => [$icon, $weight]) {
            if (!$this->resources->has($name)) {
                continue;
            }

            $resource = $this->resources->get($name);
            $children[] = new MenuItem(
                $resource->translationKey('name_p'),
                $resource->route('index'),
                icon: $icon,
                permission: AbstractResourceVoter::VIEW,
                permissionSubject: $name,
                translationDomain: $resource->translationDomain,
                weight: $weight,
            );
        }

        yield new MenuItem(
            'menu.media_manager',
            icon: 'bi-images',
            children: $children,
            translationDomain: GingermindsMediaManagerBundle::TRANSLATION_DOMAIN,
            id: self::SECTION,
            weight: 50,
        );
    }
}
