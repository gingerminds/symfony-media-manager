<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Gingerminds\CoreBundle\GingermindsCoreBundle;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use League\FlysystemBundle\FlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\Turbo\TurboBundle;
use Symfonycasts\SassBundle\SymfonycastsSassBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/packages.yaml');
        $container->import(__DIR__ . '/config/services.yaml');

        if ('test_no_basket' === $this->environment) {
            $container->import(__DIR__ . '/config/packages_no_basket.yaml');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routes.yaml');
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new DoctrineBundle();
        yield new ApiPlatformBundle();
        yield new StimulusBundle();
        yield new TurboBundle();
        yield new AutocompleteBundle();
        yield new SymfonycastsSassBundle();
        yield new FlysystemBundle();
        yield new GingermindsCoreBundle();
        yield new GingermindsMediaManagerBundle();

        if ('test' === $this->environment) {
            yield new DAMADoctrineTestBundle();
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->getVarDir() . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getVarDir() . '/log';
    }

    // PHPUnit uses its own directory so it never wipes a manually started test app.
    private function getVarDir(): string
    {
        $dir = $_SERVER['GINGERMINDS_VAR_DIR'] ?? $_ENV['GINGERMINDS_VAR_DIR'] ?? null;

        return \is_string($dir) && '' !== $dir ? $dir : sys_get_temp_dir() . '/gingerminds-media-manager-bundle';
    }
}
