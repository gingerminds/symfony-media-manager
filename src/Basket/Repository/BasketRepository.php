<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;

/**
 * @extends ServiceEntityRepository<BasketInterface>
 */
class BasketRepository extends ServiceEntityRepository
{
    /**
     * @param class-string<BasketInterface> $entityClass
     * @param int                           $ttl         days of a guest basket, 0: no expiration
     */
    public function __construct(ManagerRegistry $registry, string $entityClass, private readonly int $ttl)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * An expired basket is not found.
     */
    public function findOneByToken(string $token): ?BasketInterface
    {
        $basket = $this->findOneBy(['token' => $token]);

        return $basket instanceof BasketInterface && !$this->isExpired($basket) ? $basket : null;
    }

    public function createGuestBasket(): BasketInterface
    {
        $basket = $this->newBasket();
        $this->touch($basket);
        $this->getEntityManager()->persist($basket);
        $this->getEntityManager()->flush();

        return $basket;
    }

    public function findOrCreateForOwner(UserInterface $owner): BasketInterface
    {
        $basket = $this->findOneBy(['owner' => $owner], ['id' => 'DESC']);

        return $basket instanceof BasketInterface ? $basket : $this->createForOwner($owner);
    }

    /**
     * A user has one basket: the previous ones are deleted.
     */
    public function createForOwner(UserInterface $owner): BasketInterface
    {
        foreach ($this->findBy(['owner' => $owner]) as $previous) {
            $this->getEntityManager()->remove($previous);
        }

        $basket = $this->newBasket();
        $basket->setOwner($owner);
        $this->getEntityManager()->persist($basket);
        $this->getEntityManager()->flush();

        return $basket;
    }

    /**
     * The guest basket is deleted whatever the strategy.
     */
    public function claim(BasketInterface $guest, BasketInterface $basket, string $strategy): void
    {
        if ('replace' === $strategy) {
            array_map($basket->removeMedia(...), $basket->getMedias());
        }

        if ('ignore' !== $strategy) {
            array_map($basket->addMedia(...), $guest->getMedias());
        }

        $this->getEntityManager()->remove($guest);
        $this->getEntityManager()->flush();
    }

    /**
     * A change pushes back the expiration of a guest basket.
     */
    public function touch(BasketInterface $basket): void
    {
        if (!$basket->getOwner() instanceof UserInterface) {
            $basket->setExpiresAt(0 === $this->ttl ? null : new \DateTimeImmutable('+' . $this->ttl . ' days'));
        }
    }

    public function save(BasketInterface $basket): void
    {
        $this->touch($basket);
        $this->getEntityManager()->flush();
    }

    public function delete(BasketInterface $basket): void
    {
        $this->getEntityManager()->remove($basket);
        $this->getEntityManager()->flush();
    }

    /**
     * @return int number of deleted baskets
     */
    public function deleteExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): int
    {
        $baskets = $this->createQueryBuilder('b')
            ->where('b.owner IS NULL')
            ->andWhere('b.expiresAt IS NOT NULL')
            ->andWhere('b.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        array_map($this->getEntityManager()->remove(...), $baskets);
        $this->getEntityManager()->flush();

        return \count($baskets);
    }

    private function isExpired(BasketInterface $basket): bool
    {
        return !$basket->getOwner() instanceof UserInterface && $basket->getExpiresAt() instanceof \DateTimeImmutable && $basket->getExpiresAt() < new \DateTimeImmutable();
    }

    private function newBasket(): BasketInterface
    {
        $class = $this->getClassName();

        return new $class();
    }
}
