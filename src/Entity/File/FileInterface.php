<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\File;

use Gingerminds\CoreBundle\Model\ResourceInterface;

interface FileInterface extends ResourceInterface, \Stringable
{
    public function getId(): string;

    /**
     * Name of the storage disk (`gingerminds_media_manager.storage.disks`).
     */
    public function getDisk(): string;

    public function getPath(): string;

    public function setPath(string $path): void;

    public function getMimeType(): string;

    public function getOriginalName(): string;

    public function setOriginalName(string $originalName): void;

    public function getSize(): int;

    /**
     * Sha256 of the content, null for rows not hashed yet.
     */
    public function getHash(): ?string;

    public function setHash(?string $hash): void;

    public function isImage(): bool;
}
