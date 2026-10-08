<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\BasketProcessor;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\BasketProvider;
use Gingerminds\MediaManagerBundle\Basket\BasketArchiver;
use Gingerminds\MediaManagerBundle\Basket\Command\PurgeBasketsCommand;
use Gingerminds\MediaManagerBundle\Basket\Controller\DownloadBasketController;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketLoginResponseEnricher;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketVoter;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

/*
 * Baskets, only loaded when `basket.enabled`.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(BasketRepository::class)
        ->args([service('doctrine'), param('gingerminds_media_manager.resource.basket.entity'), param('gingerminds_media_manager.basket.ttl')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_media_manager.repository.basket', BasketRepository::class);

    $services->set('gingerminds_media_manager.basket.provider', BasketProvider::class)
        ->args([service(BasketRepository::class), service('security.authorization_checker')])
        ->tag('api_platform.state_provider');

    foreach ([BasketProcessor::CREATE, BasketProcessor::DELETE, BasketProcessor::ADD_MEDIAS, BasketProcessor::REMOVE_MEDIA] as $action) {
        $services->set('gingerminds_media_manager.basket.processor.' . $action, BasketProcessor::class)
            ->args([
                $action,
                service(BasketRepository::class),
                service(MediaRepository::class),
                service('security.helper'),
                service('request_stack'),
                param('gingerminds_media_manager.basket.claim_strategy'),
            ])
            ->tag('api_platform.state_processor');
    }

    $services->set('gingerminds_media_manager.basket.archiver', BasketArchiver::class)
        ->args([service('gingerminds_media_manager.file.storage')]);

    $services->set('gingerminds_media_manager.basket.controller.download', DownloadBasketController::class)
        ->args([service(BasketRepository::class), service('gingerminds_media_manager.basket.archiver'), service('security.authorization_checker')])
        ->tag('controller.service_arguments')
        ->public();

    $services->set('gingerminds_media_manager.basket.voter', BasketVoter::class)
        ->tag('security.voter');

    $services->set('gingerminds_media_manager.basket.login_response_enricher', BasketLoginResponseEnricher::class)
        ->args([service(BasketRepository::class)])
        ->tag('gingerminds_core.login_response_enricher');

    $services->set('gingerminds_media_manager.command.purge_baskets', PurgeBasketsCommand::class)
        ->args([service(BasketRepository::class)])
        ->tag('console.command');
};
