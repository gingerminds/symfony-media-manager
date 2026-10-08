# Forms

The form themes of the bundle are prepended to Twig: the widgets render without any setup. Their
modals (file library, media picker) are loaded once per page, when a field opens them, and shared by
every field of the page.

## `FilePickerType`

Files picked in the library. There is no upload field: the modal is the library itself, where the
user can upload the file before picking it.

```php
use Gingerminds\MediaManagerBundle\Form\File\FilePickerType;

$builder
    ->add('cover', FilePickerType::class, [
        'accept' => ['image/*'],
        'required' => false,
    ])
    ->add('attachments', FilePickerType::class, [
        'multiple' => true,
        'accept' => ['application/pdf', 'application/docx'],
    ])
    // ids in a JSON field (content blocks...)
    ->add('gallery', FilePickerType::class, [
        'multiple' => true,
        'as_id' => true,
        'accept' => ['image/*'],
    ]);
```

| Option | Default | |
|---|---|---|
| `multiple` | `false` | `FileInterface`, or a collection of `FileInterface` in their order. |
| `as_id` | `false` | The file id (a list of ids when `multiple`) instead of the entity. |
| `accept` | `[]` (every type) | Mime types, exact (`application/pdf`) or families (`image/*`). |
| `preview_preset` | `thumbnail` | Preset of the previews of the widget (else the `thumbnail` preset, or the file). |
| `start_path` | the start folder | Folder the modal opens on, relative to the library root. |

`accept` filters the library of the modal, its upload input and its type filter (only the matching
families), and is checked on submit: a file of another type, or no longer in the library, is a form
error. Only a whitelist exists: there is no option to exclude types.

The widget shows the picked files as cards, removable one by one and reordered by drag & drop when
`multiple`. Opening the modal needs `view files` (the button is disabled otherwise). A change
dispatches `gm-file-picker:change` on the widget, `event.detail.files` being the picked files.

## `MediaSelectType`

Medias picked in the media picker: search on the code and the name, filter by category (its
subcategories included), "Load more".

```php
use Gingerminds\MediaManagerBundle\Form\Media\MediaSelectType;

$builder
    ->add('booklet', MediaSelectType::class, ['required' => false])
    ->add('visuals', MediaSelectType::class, [
        'multiple' => true,
        'categories' => ['pictures', 'movies'],
        'per_page' => 12,
    ]);
```

| Option | Default | |
|---|---|---|
| `multiple` | `false` | `MediaInterface`, or a collection of `MediaInterface` in their order. |
| `as_id` | `false` | The media id (a list of ids when `multiple`). |
| `categories` | `[]` (every media) | Codes of the allowed categories, their subcategories included. One code locks the category filter of the modal. |
| `per_page` | `24` | Medias per page of the modal. |
| `collection`, `link_factory` | `null` | One collection of the media links of the entity, see below. |

A media that does not exist, or outside the allowed categories, is a form error. Opening the picker
needs `view medias`. A change dispatches `gm-media-select:change`, `event.detail.medias` being the
picked medias.

The picker reads `GET /admin/medias/search` (admin endpoint): a project scoping its public media API
(by site, language...) does not hide medias from the admin. Change it with
`resources.media.controller` (`search()`).

## Collections of medias

Ordered medias in "collections" of an entity (the "visual" and the "document" medias of a product,
in one table): a link entity extending `AbstractMediaLink` (`media` in `RESTRICT`, `collection`,
`position`) with your owner relation.

```php
use Gingerminds\MediaManagerBundle\Entity\Media\AbstractMediaLink;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

#[ORM\Entity]
#[ORM\Table(name: 'product_media')]
class ProductMedia extends AbstractMediaLink
{
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'mediaLinks')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        MediaInterface $media,
        string $collection,
    ) {
        parent::__construct($media, $collection);
    }
}

class Product
{
    /** @var Collection<int, ProductMedia> */
    #[ORM\OneToMany(targetEntity: ProductMedia::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $mediaLinks;
}
```

A field edits one collection of the links, no controller code needed:

```php
$builder->add('visuals', MediaSelectType::class, [
    'multiple' => true,
    'property_path' => 'mediaLinks',
    'collection' => 'visual',               // or a backed enum
    'link_factory' => fn (MediaInterface $media, string $collection) => new ProductMedia($product, $media, $collection),
]);
```

The other collections are left untouched, the removed links are deleted (`orphanRemoval`), the
positions follow the order of the widget.

Outside a form, `MediaCollectionSyncer::sync($product->getMediaLinks(), $medias, 'visual', $factory)`
applies a list of medias to a collection, `MediaCollectionSyncer::medias($links, 'visual')` reads it
by position.

A media used by a link, or by any relation to `MediaInterface`, cannot be deleted: declare your
direct relations to medias with `onDelete: 'RESTRICT'`.

## `MediaCategoryChoiceType`

A media category picked in the tree, indented by depth (an `EntityType`):

```php
$builder->add('category', MediaCategoryChoiceType::class, [
    'placeholder' => 'None',
    'exclude' => $category, // removes this category and its descendants
]);
```
