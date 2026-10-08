<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

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

    #[ORM\ManyToOne(targetEntity: MediaInterface::class)]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?MediaInterface $media = null;

    /**
     * Collections "visual" and "document".
     *
     * @var Collection<int, ArticleMedia>
     */
    #[ORM\OneToMany(targetEntity: ArticleMedia::class, mappedBy: 'article', cascade: ['persist'], orphanRemoval: true)]
    public Collection $mediaLinks;

    public function __construct(
        #[ORM\Column(length: 255)]
        public string $title = '',
    ) {
        $this->attachments = new ArrayCollection();
        $this->mediaLinks = new ArrayCollection();
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
