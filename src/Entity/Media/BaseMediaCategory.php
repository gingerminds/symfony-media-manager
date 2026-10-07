<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\CacheableResourceInterface;
use Gingerminds\CoreBundle\Model\CacheCascadeInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\CacheableResourceTrait;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['code'])]
abstract class BaseMediaCategory implements MediaCategoryInterface, TimestampableInterface, SortableInterface, SearchableInterface, CacheableResourceInterface, CacheCascadeInterface
{
    use CacheableResourceTrait;
    use TimestampableTrait;

    public const string GROUP_LIST = 'media_category:list';
    public const string GROUP_READ = 'media_category:read';
    public const string GROUP_TREE = 'media_category:tree';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    protected ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    protected ?string $name = null;

    #[ORM\ManyToOne(targetEntity: MediaCategoryInterface::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?MediaCategoryInterface $parent = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    protected int $position = 0;

    /**
     * @var Collection<int, MediaCategoryInterface>
     */
    #[ORM\OneToMany(targetEntity: MediaCategoryInterface::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Groups([self::GROUP_READ, self::GROUP_TREE])]
    protected Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

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

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParent(): ?MediaCategoryInterface
    {
        return $this->parent;
    }

    public function setParent(?MediaCategoryInterface $parent): void
    {
        $this->parent = $parent;
    }

    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    #[SerializedName('parent_id')]
    public function getParentId(): ?int
    {
        return $this->parent?->getId();
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getChildren(): array
    {
        return array_values($this->children->toArray());
    }

    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    public function contains(MediaCategoryInterface $category): bool
    {
        for ($current = $category; $current instanceof MediaCategoryInterface; $current = $current->getParent()) {
            if ($current === $this) {
                return true;
            }
        }

        return false;
    }

    #[Assert\Callback]
    public function validateParent(ExecutionContextInterface $context): void
    {
        if ($this->parent instanceof MediaCategoryInterface && $this->contains($this->parent)) {
            $context->buildViolation('gingerminds_media_manager.media_category.parent_cycle')
                ->atPath('parent')
                ->addViolation();
        }
    }

    public static function getSearchableFields(): array
    {
        return ['code', 'name'];
    }

    public static function getCacheKey(): string
    {
        return 'media_category';
    }

    /**
     * The media embed their category.
     */
    public static function getCascadeCacheKeys(): array
    {
        return ['media'];
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
