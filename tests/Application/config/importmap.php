<?php

declare(strict_types=1);

/**
 * @return array<string, array<string, string|bool>>
 */
return [
    'gingerminds-core-admin' => [
        'path' => 'gingerminds-core/admin.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@symfony/stimulus-bundle' => ['path' => '@symfony/stimulus-bundle/loader.js'],
    '@symfony/ux-autocomplete' => ['path' => '@symfony/ux-autocomplete/controller.js'],
    'bootstrap' => ['version' => '5.3.8'],
    '@popperjs/core' => ['version' => '2.11.8'],
    'bootstrap-icons/font/bootstrap-icons.min.css' => ['version' => '1.13.1', 'type' => 'css'],
    'sortablejs' => ['version' => '1.15.7'],
    'tom-select' => ['version' => '2.6.2'],
    '@orchidjs/sifter' => ['version' => '1.1.0'],
    '@orchidjs/unicode-variants' => ['version' => '1.1.2'],
    'tom-select/dist/css/tom-select.bootstrap5.css' => ['version' => '2.6.2', 'type' => 'css'],
];
