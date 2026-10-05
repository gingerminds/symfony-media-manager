<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

$resource = static function (NodeBuilder $children, string $name, string $baseClass): void {
    $children
        ->arrayNode($name)
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('entity')
                    ->info('Entity class, extending the bundle Base' . $baseClass . ' (null: the bundle one).')
                    ->defaultNull()
                ->end()
                ->scalarNode('controller')->defaultNull()->end()
                ->scalarNode('form')->defaultNull()->end()
            ->end()
        ->end();
};

$storage = static function (NodeBuilder $children): void {
    $children
        ->arrayNode('storage')
            ->info('Where the files are stored. Every file row keeps the name of its disk (files.disk).')
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(static fn (array $storage): bool => !isset($storage['disks'][$storage['default_disk']]))
                ->then(static function (array $storage): never {
                    throw new InvalidArgumentException(sprintf('The storage default_disk "%s" is not declared in storage.disks.', $storage['default_disk']));
                })
            ->end()
            ->children()
                ->scalarNode('default_disk')
                    ->info('Disk of the new files.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('disks')
                    ->info('Disk name => Flysystem storage service id (league/flysystem-bundle).'
                        . ' Declare the disk names of a migrated Laravel database (e.g. "public") to keep its files.disk values.')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(['default' => 'gingerminds_media_manager.storage.default'])
                    ->requiresAtLeastOneElement()
                ->end()
            ->end()
        ->end();
};

$library = static function (NodeBuilder $children): void {
    $children
        ->arrayNode('library')
            ->info('File library: the single place files are uploaded to, browsed and picked from.')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('root')
                    ->info('Root directory of the library on the disks, relative (no leading slash, no "..").')
                    ->defaultValue('library')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (string $root): bool => str_starts_with($root, '/') || in_array('..', explode('/', $root), true))
                        ->thenInvalid('The library root must be a relative path without "..", got %s.')
                    ->end()
                ->end()
                ->integerNode('max_upload_size')
                    ->info('Maximum size of an uploaded file, in KB. Keep it below the PHP upload_max_filesize / post_max_size and the web server limits.')
                    ->defaultValue(51200)
                    ->min(1)
                ->end()
                ->arrayNode('allowed_mimes')
                    ->info('Mime types accepted on upload, after normalization (Office documents are "application/<extension>").')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'image/svg+xml',
                        'application/pdf',
                        'application/zip',
                        'application/docx',
                        'application/xlsx',
                        'application/pptx',
                    ])
                    ->requiresAtLeastOneElement()
                ->end()
                ->integerNode('per_page')
                    ->info('Files per page in the library browser.')
                    ->defaultValue(48)
                    ->min(1)
                    ->max(200)
                ->end()
            ->end()
        ->end();
};

$imageFormats = ['jpg', 'pjpg', 'png', 'gif', 'webp', 'avif'];

$images = static function (NodeBuilder $children) use ($imageFormats): void {
    $children
        ->arrayNode('images')
            ->info('Image presets rendered by Glide (GET /api/files/{id}/{preset}).')
            ->addDefaultsIfNotSet()
            ->children()
                ->enumNode('driver')
                    ->info('Image library used by Glide.')
                    ->values(['imagick', 'gd'])
                    ->defaultValue('imagick')
                ->end()
                ->enumNode('default_format')
                    ->info('Output format of the presets without their own "fm".')
                    ->values($imageFormats)
                    ->defaultValue('webp')
                ->end()
                ->scalarNode('cache_prefix')
                    ->info('Directory of the rendered presets, on the disk of each file.')
                    ->defaultValue('.cache')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('presets')
                    ->info('Preset name => Glide parameters (w, h, fit, q, fm, and any other Glide parameter). Replaces the default presets when set.')
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->ifTrue(static fn (array $presets): bool => [] !== array_filter(
                            array_keys($presets),
                            static fn (int|string $name): bool => 1 !== preg_match('/^[a-z0-9_-]+$/', (string) $name),
                        ))
                        ->thenInvalid('Preset names are URL segments: lowercase letters, digits, "_" and "-" only, got %s.')
                    ->end()
                    ->arrayPrototype()
                        ->ignoreExtraKeys(false)
                        ->children()
                            ->integerNode('w')->min(1)->end()
                            ->integerNode('h')->min(1)->end()
                            ->enumNode('fit')->values(['contain', 'max', 'fill', 'fill-max', 'stretch', 'crop'])->end()
                            ->integerNode('q')->min(0)->max(100)->end()
                            ->enumNode('fm')->values($imageFormats)->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'micro' => ['w' => 25, 'h' => 25, 'fit' => 'crop', 'q' => 70],
                        'thumbnail' => ['w' => 150, 'h' => 150, 'fit' => 'crop', 'q' => 80],
                        'card' => ['w' => 400, 'h' => 300, 'fit' => 'contain', 'q' => 85],
                        'hero' => ['w' => 1280, 'h' => 720, 'fit' => 'crop', 'q' => 90],
                    ])
                ->end()
            ->end()
        ->end();
};

$basket = static function (NodeBuilder $children): void {
    $children
        ->arrayNode('basket')
            ->info('Download baskets of medias (API).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Master switch of the feature: entities, API operations and login enrichment.')
                    ->defaultTrue()
                ->end()
                ->enumNode('claim_strategy')
                    ->info('What happens to an anonymous basket when its owner logs in: merged into, replacing, or ignored by the user basket.')
                    ->values(['merge', 'replace', 'ignore'])
                    ->defaultValue('merge')
                ->end()
            ->end()
        ->end();
};

$resources = static function (NodeBuilder $children) use ($resource): void {
    $node = $children->arrayNode('resources')
        ->info('Overridable resources of the bundle, registered as gingerminds_core resources.')
        ->addDefaultsIfNotSet()
        ->children();
    $resource($node, 'media', 'Media');
    $resource($node, 'media_category', 'MediaCategory');
    $resource($node, 'file', 'File');
    $resource($node, 'basket', 'Basket');
    $node->end()->end();
};

return static function (DefinitionConfigurator $definition) use ($storage, $library, $images, $basket, $resources): void {
    $children = $definition->rootNode()->children();

    $storage($children);
    $library($children);
    $images($children);

    $children
        ->integerNode('files_rate_limit')
            ->info('Requests per minute and per IP on GET /api/files/* (0: no limit).')
            ->defaultValue(600)
            ->min(0)
        ->end();

    $basket($children);
    $resources($children);
};
