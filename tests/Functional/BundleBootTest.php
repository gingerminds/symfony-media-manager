<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional;

use Symfony\Component\HttpKernel\KernelInterface;

final class BundleBootTest extends ApiTestCase
{
    public function testBundleIsRegisteredWithItsConfiguration(): void
    {
        /** @var KernelInterface $kernel */
        $kernel = self::getContainer()->get('kernel');

        self::assertArrayHasKey('GingermindsMediaManagerBundle', $kernel->getBundles());
        self::assertSame(['default' => 'gingerminds_media_manager.storage.default'], self::getContainer()->getParameter('gingerminds_media_manager.storage.disks'));
        self::assertSame('webp', self::getContainer()->getParameter('gingerminds_media_manager.images.default_format'));
        self::assertTrue(self::getContainer()->getParameter('gingerminds_media_manager.basket.enabled'));
    }

    public function testDefaultStorageIsAvailable(): void
    {
        self::assertTrue(self::getContainer()->has('gingerminds_media_manager.storage.default'));
    }

    public function testAdminDashboardRenders(): void
    {
        $this->client->loginUser($this->fixtures->user('admin@example.com', superAdmin: true), 'admin');
        $this->client->request('GET', '/admin/');

        self::assertResponseIsSuccessful();
    }
}
