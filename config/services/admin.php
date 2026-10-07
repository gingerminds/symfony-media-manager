<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Form\Media\MediaCategoryType;
use Gingerminds\MediaManagerBundle\Menu\MediaManagerAdminMenuProvider;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Gingerminds\MediaManagerBundle\Security\Voter\MediaCategoryVoter;

/*
 * Admin: menu, voters and form types.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.admin_menu.provider', MediaManagerAdminMenuProvider::class)
        ->args([service('gingerminds_core.resource_registry')])
        ->tag('gingerminds_core.admin_menu_provider');

    $services->set('gingerminds_media_manager.security.voter.media_category', MediaCategoryVoter::class)
        ->tag('security.voter');

    $services->set('gingerminds_media_manager.form.type.media_category', MediaCategoryType::class)
        ->args([service('gingerminds_core.resource_registry'), service(MediaCategoryRepository::class)])
        ->tag('form.type');
};
