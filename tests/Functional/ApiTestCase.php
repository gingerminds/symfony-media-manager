<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected Fixtures $fixtures;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->fixtures = new Fixtures(
            $container->get(EntityManagerInterface::class),
            $container->get(UserPasswordHasherInterface::class),
        );
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    protected function api(string $method, string $uri, ?string $token = null, ?array $json = null, string $contentType = 'application/json', ?string $locale = null): array
    {
        $server = ['HTTP_ACCEPT' => 'application/ld+json'];

        if (null !== $locale) {
            $server['HTTP_ACCEPT_LANGUAGE'] = $locale;
        }

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        if (null !== $json) {
            $server['CONTENT_TYPE'] = $contentType;
        }

        $this->client->request($method, $uri, server: $server, content: null !== $json ? json_encode($json, \JSON_THROW_ON_ERROR) : null);
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame($expected, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }
}
