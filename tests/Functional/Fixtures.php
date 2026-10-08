<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class Fixtures
{
    public const string PASSWORD = 'password123';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function permission(string $name): Permission
    {
        $permission = $this->entityManager->getRepository(Permission::class)->findOneBy(['name' => $name]);

        if (null === $permission) {
            $permission = new Permission();
            $permission->setName($name);
            $this->entityManager->persist($permission);
            $this->entityManager->flush();
        }

        return $permission;
    }

    /**
     * @param list<string> $permissions
     */
    public function role(string $name, array $permissions = []): Role
    {
        $role = new Role();
        $role->setName($name);

        foreach ($permissions as $permission) {
            $role->addPermission($this->permission($permission));
        }

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $role;
    }

    /**
     * @param list<string> $permissions permissions granted through a dedicated role
     */
    public function user(string $email, array $permissions = [], bool $superAdmin = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

        if ($superAdmin) {
            $user->addRoleEntity($this->role(User::SUPER_ADMIN_ROLE));
        } elseif ([] !== $permissions) {
            $user->addRoleEntity($this->role('Role of ' . $email, $permissions));
        }

        $contributor = new Contributor();
        $contributor->setFirstname('First');
        $contributor->setLastname(ucfirst(strtok($email, '@') ?: 'Last'));
        $contributor->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($contributor);
        $this->entityManager->flush();

        return $user;
    }

    public function token(User $user): string
    {
        $plain = ApiToken::generatePlainToken();
        $this->entityManager->persist(new ApiToken($user, 'test', $plain));
        $this->entityManager->flush();

        return $plain;
    }

    public function mediaCategory(string $code, ?MediaCategory $parent = null, int $position = 0): MediaCategory
    {
        $category = new MediaCategory();
        $category->setCode($code);
        $category->setName(ucfirst($code));
        $category->setParent($parent);
        $category->setPosition($position);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function media(FileInterface $file, ?string $name = null, ?MediaCategory $category = null, ?FileInterface $thumbnail = null, ?string $code = null): Media
    {
        $media = new Media();
        $media->setCode($code ?? 'media-' . $file->getId());
        $media->setFile($file);
        $media->setName($name);
        $media->setCategory($category);
        $media->setThumbnail($thumbnail);
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }
}
