<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\File;

use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
#[ORM\Index(name: 'files_hash_index', columns: ['hash'])]
class File extends BaseFile
{
}
