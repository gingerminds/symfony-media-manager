<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\File;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Symfony\Component\Uid\Uuid;

#[ORM\MappedSuperclass]
abstract class BaseFile implements FileInterface, TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    protected string $id;

    public function __construct(
        #[ORM\Column(length: 255)]
        protected string $disk,
        #[ORM\Column(length: 255)]
        protected string $path,
        #[ORM\Column(length: 255)]
        protected string $mimeType,
        #[ORM\Column(length: 255)]
        protected string $originalName,
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        protected int $size,
        #[ORM\Column(length: 64, nullable: true, options: ['fixed' => true])]
        protected ?string $hash = null,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function __toString(): string
    {
        return $this->originalName;
    }
}
