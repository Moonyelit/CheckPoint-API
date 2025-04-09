<?php

namespace App\Entity;

use App\Repository\StatsUserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatsUserRepository::class)]
class StatsUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'statsUser', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?int $favoriteGenreId = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalPlaytime = null;

    #[ORM\Column(nullable: true)]
    private ?int $gamesCompleted = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalAchievements = null;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $xpPoints = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $userRank = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getFavoriteGenreId(): ?int
    {
        return $this->favoriteGenreId;
    }

    public function setFavoriteGenreId(int $favoriteGenreId): static
    {
        $this->favoriteGenreId = $favoriteGenreId;

        return $this;
    }

    public function getTotalPlaytime(): ?int
    {
        return $this->totalPlaytime;
    }

    public function setTotalPlaytime(?int $totalPlaytime): static
    {
        $this->totalPlaytime = $totalPlaytime;

        return $this;
    }

    public function getGamesCompleted(): ?int
    {
        return $this->gamesCompleted;
    }

    public function setGamesCompleted(?int $gamesCompleted): static
    {
        $this->gamesCompleted = $gamesCompleted;

        return $this;
    }

    public function getTotalAchievements(): ?int
    {
        return $this->totalAchievements;
    }

    public function setTotalAchievements(?int $totalAchievements): static
    {
        $this->totalAchievements = $totalAchievements;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getXpPoints(): ?int
    {
        return $this->xpPoints;
    }

    public function setXpPoints(?int $xpPoints): static
    {
        $this->xpPoints = $xpPoints;

        return $this;
    }

    public function getUserRank(): ?string
    {
        return $this->userRank;
    }

    public function setUserRank(?string $userRank): static
    {
        $this->userRank = $userRank;

        return $this;
    }
}
