<?php

namespace App\Repository;

use App\Entity\UserWallpaper;
use App\Entity\User;
use App\Entity\Wallpaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserWallpaper>
 */
class UserWallpaperRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserWallpaper::class);
    }

    /**
     * Récupère tous les wallpapers actifs d'un utilisateur
     */
    public function findActiveWallpapersByUser(User $user): array
    {
        return $this->createQueryBuilder('uw')
            ->leftJoin('uw.wallpaper', 'w')
            ->leftJoin('w.game', 'g')
            ->addSelect('w', 'g')
            ->where('uw.user = :user')
            ->andWhere('uw.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('uw.selectedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a déjà sélectionné un wallpaper
     */
    public function hasUserSelectedWallpaper(User $user, Wallpaper $wallpaper): bool
    {
        $result = $this->createQueryBuilder('uw')
            ->select('COUNT(uw.id)')
            ->where('uw.user = :user')
            ->andWhere('uw.wallpaper = :wallpaper')
            ->andWhere('uw.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('wallpaper', $wallpaper)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Récupère un wallpaper utilisateur par user et wallpaper
     */
    public function findByUserAndWallpaper(User $user, Wallpaper $wallpaper): ?UserWallpaper
    {
        return $this->createQueryBuilder('uw')
            ->where('uw.user = :user')
            ->andWhere('uw.wallpaper = :wallpaper')
            ->setParameter('user', $user)
            ->setParameter('wallpaper', $wallpaper)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Désactive tous les wallpapers d'un utilisateur
     */
    public function deactivateAllUserWallpapers(User $user): int
    {
        return $this->createQueryBuilder('uw')
            ->update()
            ->set('uw.isActive', 'false')
            ->where('uw.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère le nombre de wallpapers actifs d'un utilisateur
     */
    public function countActiveWallpapersByUser(User $user): int
    {
        return $this->createQueryBuilder('uw')
            ->select('COUNT(uw.id)')
            ->where('uw.user = :user')
            ->andWhere('uw.isActive = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
} 