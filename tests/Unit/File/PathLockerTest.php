<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\File;

use Gingerminds\MediaManagerBundle\File\PathLocker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class PathLockerTest extends TestCase
{
    private LockFactory $locks;
    private PathLocker $locker;

    protected function setUp(): void
    {
        $this->locks = new LockFactory(new InMemoryStore());
        $this->locker = new PathLocker($this->locks);
    }

    public function testTheSuffixedNamesShareOneResource(): void
    {
        $resource = $this->locker->resource('default', 'library/fr', 'photo.jpg');

        self::assertSame($resource, $this->locker->resource('default', 'library/fr', 'photo-1.jpg'));
        self::assertSame($resource, $this->locker->resource('default', 'library/fr', 'Photo-1-2.JPG'));
        self::assertNotSame($resource, $this->locker->resource('default', 'library/fr', 'photo.png'));
        self::assertNotSame($resource, $this->locker->resource('default', 'library/en', 'photo.jpg'));
        self::assertNotSame($resource, $this->locker->resource('public', 'library/fr', 'photo.jpg'));
    }

    public function testTheResourcesAreLockedDuringTheOperationOnly(): void
    {
        $targets = [['default', 'library', 'a.txt'], ['default', 'library', 'b.txt'], ['default', 'library', 'a-1.txt']];

        $result = $this->locker->synchronized($targets, function (): string {
            self::assertFalse($this->locks->createLock($this->locker->resource('default', 'library', 'a.txt'))->acquire());
            self::assertFalse($this->locks->createLock($this->locker->resource('default', 'library', 'b.txt'))->acquire());

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertTrue($this->locks->createLock($this->locker->resource('default', 'library', 'a.txt'))->acquire());
    }

    public function testTheLocksAreReleasedOnFailure(): void
    {
        try {
            $this->locker->synchronized([['default', 'library', 'a.txt']], static fn (): never => throw new \RuntimeException('failed'));
        } catch (\RuntimeException) {
        }

        self::assertTrue($this->locks->createLock($this->locker->resource('default', 'library', 'a.txt'))->acquire());
    }

    public function testAMovedDirectoryBlocksTheWritesUnderIt(): void
    {
        $this->locker->synchronized([], function (): void {
            self::assertFalse($this->locks->createLock($this->locker->directoryResource('default', 'library/docs'))->acquireRead());
            self::assertTrue($this->locks->createLock($this->locker->directoryResource('default', 'library'))->acquireRead(), 'Shared parent.');
        }, [['default', 'library/docs']]);

        $this->locker->synchronized([['default', 'library/docs/old', 'a.txt']], function (): void {
            self::assertTrue($this->locks->createLock($this->locker->directoryResource('default', 'library/docs/old'))->acquireRead(), 'Concurrent writes.');
            self::assertFalse($this->locks->createLock($this->locker->directoryResource('default', 'library/docs'))->acquire(), 'No move of a parent.');
        });
    }

    public function testAContentIsUploadedOnceAtATime(): void
    {
        $this->locker->content('abc', function (): void {
            self::assertFalse($this->locks->createLock('gingerminds_media_manager.path.content.abc')->acquire());
            self::assertTrue($this->locks->createLock('gingerminds_media_manager.path.content.def')->acquire());
        });
    }
}
