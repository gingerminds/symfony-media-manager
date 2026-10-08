<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\CacheableResourceInterface;
use Gingerminds\CoreBundle\Model\EagerLoadableInterface;
use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\Trait\CacheableResourceTrait;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['code'])]
abstract class BaseMedia implements MediaInterface, SortableInterface, SearchableInterface, FilterableInterface, EagerLoadableInterface, CacheableResourceInterface
{
    use CacheableResourceTrait;
    use TimestampableTrait;

    public const string GROUP_LIST = 'media:list';
    public const string GROUP_READ = 'media:read';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    protected ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    protected ?string $name = null;

    // RESTRICT: a file is never deleted with the media, nor a media with its file.
    #[ORM\ManyToOne(targetEntity: FileInterface::class)]
    #[ORM\JoinColumn(name: 'file_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    protected ?FileInterface $file = null;

    #[ORM\ManyToOne(targetEntity: FileInterface::class)]
    #[ORM\JoinColumn(name: 'thumbnail_id', onDelete: 'RESTRICT')]
    protected ?FileInterface $thumbnail = null;

    #[ORM\ManyToOne(targetEntity: MediaCategoryInterface::class)]
    #[ORM\JoinColumn(name: 'media_category_id', onDelete: 'SET NULL')]
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

    /**
     * Always the id, never the path (Laravel sent the path of a non-image file).
     */
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('file')]
    public function getFileId(): ?string
    {
        return $this->file?->getId();
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('file_reference')]
    public function getFileReference(): ?string
    {
        return $this->file?->getId();
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('file_size')]
    public function getFileSize(): ?int
    {
        return $this->file?->getSize();
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('file_type')]
    public function getFileType(): ?string
    {
        return $this->file?->getMimeType();
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('thumbnail_reference')]
    public function getThumbnailReference(): ?string
    {
        return $this->thumbnail?->getId();
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('thumbnail_size')]
    public function getThumbnailSize(): ?int
    {
        return $this->thumbnail?->getSize();
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('media_category_id')]
    public function getCategoryId(): ?int
    {
        return $this->category?->getId();
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

    public static function getSearchableFields(): array
    {
        return ['code', 'name'];
    }

    /**
     * `entity` is the bundle class: MediaController replaces it with the configured one.
     */
    public static function getFilters(): array
    {
        return [
            'category' => [
                'type' => 'select-entity',
                'label' => 'media.field.category',
                'entity' => MediaCategory::class,
                'resource' => 'media_category',
                'multiple' => true,
            ],
        ];
    }

    public static function getEagerLoads(): array
    {
        return ['file', 'thumbnail', 'category'];
    }

    public static function getCacheKey(): string
    {
        return 'media';
    }

    public function __toString(): string
    {
        return $this->name ?? (string) $this->file;
    }
}
