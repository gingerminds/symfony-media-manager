<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Entity;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;

#[ORM\MappedSuperclass]
abstract class BaseBasket implements BasketInterface
{
    use TimestampableTrait;

    public const string GROUP_READ = 'basket:read';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    protected ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_READ])]
    protected string $token;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(name: 'owner_id', onDelete: 'CASCADE')]
    protected ?UserInterface $owner = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    #[SerializedName('expires_at')]
    protected ?\DateTimeImmutable $expiresAt = null;

    /**
     * @var Collection<int, MediaInterface>
     */
    #[ORM\ManyToMany(targetEntity: MediaInterface::class)]
    #[ORM\JoinTable(name: 'basket_media')]
    #[ORM\JoinColumn(name: 'basket_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'media_id', onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ])]
    protected Collection $medias;

    public function __construct()
    {
        $this->token = Uuid::v4()->toRfc4122();
        $this->medias = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getOwner(): ?UserInterface
    {
        return $this->owner;
    }

    public function setOwner(?UserInterface $owner): void
    {
        $this->owner = $owner;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getMedias(): array
    {
        return array_values($this->medias->toArray());
    }

    public function hasMedia(MediaInterface $media): bool
    {
        return $this->medias->contains($media);
    }

    public function addMedia(MediaInterface $media): void
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
        }
    }

    public function removeMedia(MediaInterface $media): void
    {
        $this->medias->removeElement($media);
    }
}
