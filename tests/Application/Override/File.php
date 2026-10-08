<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Override;

use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Entity\File\BaseFile;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
class File extends BaseFile
{
}
