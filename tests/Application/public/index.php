<?php

declare(strict_types=1);

use Gingerminds\MediaManagerBundle\Tests\Application\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'test', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
