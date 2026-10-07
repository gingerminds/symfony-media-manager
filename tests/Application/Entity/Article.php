<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * Admin resource referencing files through associations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'articles')]
class Article implements ResourceInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FileInterface::class)]
    public ?FileInterface $cover = null;

    /**
     * @var Collection<int, FileInterface>
     */
    #[ORM\ManyToMany(targetEntity: FileInterface::class)]
    #[ORM\JoinTable(name: 'article_attachments')]
    public Collection $attachments;

    public function __construct(
        #[ORM\Column(length: 255)]
        public string $title = '',
    ) {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
