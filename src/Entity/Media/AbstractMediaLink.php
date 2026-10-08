<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use Doctrine\ORM\Mapping as ORM;

/**
 * Link of a project entity to a media, extended with the owner relation:
 *
 *     #[ORM\Entity]
 *     #[ORM\Table(name: 'product_media')]
 *     class ProductMedia extends AbstractMediaLink
 *     {
 *         public function __construct(
 *             #[ORM\ManyToOne(inversedBy: 'mediaLinks')]
 *             #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
 *             private Product $product,
 *             MediaInterface $media,
 *             string $collection,
 *         ) {
 *             parent::__construct($media, $collection);
 *         }
 *     }
 *
 * A used media cannot be deleted (RESTRICT).
 */
#[ORM\MappedSuperclass]
abstract class AbstractMediaLink implements MediaLinkInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: MediaInterface::class)]
        #[ORM\JoinColumn(name: 'media_id', nullable: false, onDelete: 'RESTRICT')]
        protected MediaInterface $media,
        #[ORM\Column(length: 64)]
        protected string $collection,
        #[ORM\Column(options: ['default' => 0])]
        protected int $position = 0,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMedia(): MediaInterface
    {
        return $this->media;
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
