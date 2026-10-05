<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Gingerminds\MediaManagerBundle\Tests\Application\Kernel;
use Symfony\Component\Filesystem\Filesystem;

require __DIR__ . '/../vendor/autoload.php';

// Isolated from any manually started test app (see Kernel::getVarDir()).
$_SERVER['GINGERMINDS_VAR_DIR'] = $_ENV['GINGERMINDS_VAR_DIR'] = __DIR__ . '/../var/phpunit';

// Fresh cache and SQLite schema for every run.
$kernel = new Kernel('test', true);
new Filesystem()->remove($kernel->getCacheDir());
$kernel->boot();

/** @var EntityManagerInterface $entityManager */
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$schemaTool = new SchemaTool($entityManager);
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();
$schemaTool->dropDatabase();
$schemaTool->createSchema($metadata);

$kernel->shutdown();
