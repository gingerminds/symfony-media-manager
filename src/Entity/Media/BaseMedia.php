<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\Cacheable;
use Gingerminds\CoreBundle\Model\EagerLoad;
use Gingerminds\CoreBundle\Model\Filter;
use Gingerminds\CoreBundle\Model\Searchable;
use Gingerminds\CoreBundle\Model\Sortable;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['code'])]
#[Cacheable(key: 'media')]
abstract class BaseMedia implements MediaInterface
{
    use MediaApiFieldsTrait;
    use TimestampableTrait;

    public const string GROUP_LIST = 'media:list';
    public const string GROUP_READ = 'media:read';

    // The medias of a basket (BaseBasket::GROUP_READ).
    public const string GROUP_BASKET = 'basket:read';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_BASKET])]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_BASKET])]
    #[Sortable]
    #[Searchable]
    protected ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_BASKET])]
    #[Sortable]
    #[Searchable]
    protected ?string $name = null;

    // RESTRICT: a file is never deleted with the media, nor a media with its file.
    #[ORM\ManyToOne(targetEntity: FileInterface::class)]
    #[ORM\JoinColumn(name: 'file_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    #[EagerLoad]
    protected ?FileInterface $file = null;

    #[ORM\ManyToOne(targetEntity: FileInterface::class)]
    #[ORM\JoinColumn(name: 'thumbnail_id', onDelete: 'RESTRICT')]
    #[EagerLoad]
    protected ?FileInterface $thumbnail = null;

    #[ORM\ManyToOne(targetEntity: MediaCategoryInterface::class)]
    #[ORM\JoinColumn(name: 'media_category_id', onDelete: 'SET NULL')]
    #[EagerLoad]
    #[Sortable(paths: ['name'])]
    #[Filter(label: 'media.field.category', multiple: true, resource: 'media_category')]
    protected ?MediaCategoryInterface $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = trim($code);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = null === $name || '' === trim($name) ? null : trim($name);
    }

    #[Ignore]
    public function getFile(): ?FileInterface
    {
        return $this->file;
    }

    public function setFile(?FileInterface $file): void
    {
        $this->file = $file;
    }

    #[Ignore]
    public function getThumbnail(): ?FileInterface
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?FileInterface $thumbnail): void
    {
        $this->thumbnail = $thumbnail;
    }

    #[Ignore]
    public function getCategory(): ?MediaCategoryInterface
    {
        return $this->category;
    }

    public function setCategory(?MediaCategoryInterface $category): void
    {
        $this->category = $category;
    }

    #[Ignore]
    public function getPreview(): ?FileInterface
    {
        return true === $this->file?->isImage() ? $this->file : $this->thumbnail;
    }

    #[Assert\Callback]
    public function validateThumbnail(ExecutionContextInterface $context): void
    {
        if ($this->thumbnail instanceof FileInterface && !$this->thumbnail->isImage()) {
            $context->buildViolation('gingerminds_media_manager.media.thumbnail_not_image')
                ->atPath('thumbnail')
                ->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->name ?? (string) $this->file;
    }
}
