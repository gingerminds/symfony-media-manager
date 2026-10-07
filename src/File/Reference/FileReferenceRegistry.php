<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * Who uses which file, over every reference source.
 */
class FileReferenceRegistry
{
    /**
     * @param iterable<FileReferenceSourceInterface> $sources
     * @param iterable<FileUsageResolverInterface>   $resolvers by priority
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly iterable $resolvers,
    ) {
    }

    /**
     * In one pass for all the files (no query per file).
     *
     * @param list<FileInterface> $files
     *
     * @return array<string, list<FileUsage>> by file id, unused files included
     */
    public function usages(array $files): array
    {
        $ids = array_map(static fn (FileInterface $file): string => $file->getId(), $files);
        $usages = array_fill_keys($ids, []);

        foreach ($this->sources as $source) {
            foreach ($source->references($ids) as $reference) {
                $usage = $this->resolve($reference);

                if ($usage instanceof FileUsage && !\in_array($usage, $usages[$reference->fileId] ?? [], false)) {
                    $usages[$reference->fileId][] = $usage;
                }
            }
        }

        return $usages;
    }

    public function isUsed(FileInterface $file): bool
    {
        foreach ($this->sources as $source) {
            if ([] !== $source->references([$file->getId()])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function usedIds(): array
    {
        $ids = [];

        foreach ($this->sources as $source) {
            foreach ($source->usedIds() as $id) {
                $ids[$id] = true;
            }
        }

        return array_map(strval(...), array_keys($ids));
    }

    /**
     * @return int number of updated entities
     */
    public function replace(FileInterface $old, FileInterface $new): int
    {
        $updated = 0;

        foreach ($this->sources as $source) {
            $updated += $source->replace($old, $new);
        }

        return $updated;
    }

    private function resolve(FileReference $reference): ?FileUsage
    {
        foreach ($this->resolvers as $resolver) {
            $usage = $resolver->resolve($reference);

            if (null !== $usage) {
                return $usage;
            }
        }

        return null;
    }
}
