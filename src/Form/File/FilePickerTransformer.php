<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\File;

use Doctrine\Common\Collections\ArrayCollection;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * Files (or their ids with `as_id`) <=> the comma separated ids of the hidden input.
 *
 * @implements DataTransformerInterface<mixed, string>
 */
final readonly class FilePickerTransformer implements DataTransformerInterface
{
    /**
     * @param list<string> $accept
     */
    public function __construct(
        private FileRepository $files,
        private bool $multiple,
        private bool $asId,
        private array $accept,
    ) {
    }

    public function transform(mixed $value): string
    {
        $values = is_iterable($value) ? [...$value] : [$value];

        return implode(',', array_filter(array_map(
            static fn (mixed $file): ?string => $file instanceof FileInterface ? $file->getId() : (\is_string($file) ? $file : null),
            $values,
        )));
    }

    public function reverseTransform(mixed $value): mixed
    {
        $ids = self::ids(\is_string($value) ? $value : '');

        if (!$this->multiple && \count($ids) > 1) {
            throw new TransformationFailedException('A single file is expected.');
        }

        $files = $this->find($ids);

        if ($this->multiple) {
            return $this->asId ? $ids : new ArrayCollection($files);
        }

        if ([] === $files) {
            return null;
        }

        return $this->asId ? $files[0]->getId() : $files[0];
    }

    /**
     * @return list<string>
     */
    public static function ids(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value)))));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<FileInterface> in the order of the ids
     */
    private function find(array $ids): array
    {
        $valid = array_values(array_filter($ids, Uuid::isValid(...)));
        $found = [];

        foreach ([] === $valid ? [] : $this->files->findBy(['id' => $valid]) as $file) {
            $found[$file->getId()] = $file;
        }

        $files = [];

        foreach ($ids as $id) {
            $file = $found[$id] ?? null;

            if (!$file instanceof FileInterface) {
                throw $this->failure('gingerminds_media_manager.file_picker.not_found', ['{{ id }}' => $id]);
            }

            if (!MimeTypePatterns::matches($file->getMimeType(), $this->accept)) {
                throw $this->failure('gingerminds_media_manager.file_picker.mime_type', ['{{ name }}' => $file->getOriginalName(), '{{ types }}' => implode(', ', $this->accept)]);
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function failure(string $message, array $parameters): TransformationFailedException
    {
        return new TransformationFailedException($message, 0, null, $message, $parameters);
    }
}
