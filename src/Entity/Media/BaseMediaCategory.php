<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\Cacheable;
use Gingerminds\CoreBundle\Model\CacheCascade;
use Gingerminds\CoreBundle\Model\Searchable;
use Gingerminds\CoreBundle\Model\Sortable;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Gingerminds\CoreBundle\Validator\NoTreeCycle;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['code'])]
#[NoTreeCycle]
#[Cacheable(key: 'media_category')]
// The media embed their category.
#[CacheCascade(['media'])]
abstract class BaseMediaCategory implements MediaCategoryInterface
{
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
    #[Sortable]
    #[Searchable]
    protected ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    #[Sortable]
    #[Searchable]
    protected ?string $name = null;

    #[ORM\ManyToOne(targetEntity: MediaCategoryInterface::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?MediaCategoryInterface $parent = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_TREE])]
    #[Sortable]
    protected int $position = 0;

    /**
     * @var Collection<int, MediaCategoryInterface>
     */
    #[ORM\OneToMany(targetEntity: MediaCategoryInterface::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
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

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
