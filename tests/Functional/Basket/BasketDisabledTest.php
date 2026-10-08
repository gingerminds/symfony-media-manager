<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Basket;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\Basket;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * `basket.enabled: false` (test_no_basket environment) removes everything.
 */
final class BasketDisabledTest extends KernelTestCase
{
    public function testNothingIsLeft(): void
    {
        self::bootKernel(['environment' => 'test_no_basket']);
        $container = self::getContainer();

        self::assertFalse($container->getParameter('gingerminds_media_manager.basket.enabled'));
        self::assertSame([], array_filter(
            array_map(static fn ($route): string => $route->getPath(), $container->get(RouterInterface::class)->getRouteCollection()->all()),
            static fn (string $path): bool => str_contains($path, 'basket'),
        ));
        self::assertNotContains(Basket::class, iterator_to_array($container->get(ResourceNameCollectionFactoryInterface::class)->create()));
        self::assertTrue($container->get(EntityManagerInterface::class)->getMetadataFactory()->isTransient(Basket::class), 'Not mapped.');
        self::assertFalse($container->has('gingerminds_media_manager.basket.login_response_enricher'));
        self::assertFalse($container->has('gingerminds_media_manager.basket.voter'));
    }
}
