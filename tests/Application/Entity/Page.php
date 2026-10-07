<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * File ids inside JSON content blocks, without any association.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pages')]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<mixed> $content
     */
    public function __construct(
        #[ORM\Column(length: 255)]
        public string $title = '',
        #[ORM\Column(type: Types::JSON)]
        public array $content = [],
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
