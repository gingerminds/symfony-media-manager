<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\ApiPlatform\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Documents the `{preset}` path parameter as the list of the configured presets.
 */
final readonly class FilePresetOpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private array $presets,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            $get = $pathItem->getGet();

            if (!str_contains((string) $path, '{preset}') || null === $get) {
                continue;
            }

            $parameters = array_map(
                fn (Parameter $parameter): Parameter => 'preset' === $parameter->getName()
                    ? $parameter->withSchema(['type' => 'string', 'enum' => array_map(strval(...), array_keys($this->presets))])
                    : $parameter,
                $get->getParameters() ?? [],
            );

            $paths->addPath((string) $path, $pathItem->withGet($get->withParameters($parameters)));
        }

        return $openApi;
    }
}
