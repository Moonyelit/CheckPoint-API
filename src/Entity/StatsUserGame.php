<?php

namespace App\Entity;

use App\Repository\StatsUserGameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

#[ORM\Entity(repositoryClass: StatsUserGameRepository::class)]
#[ORM\Table(name: 'stats_user_game')]
#[ORM\UniqueConstraint(name: 'user_game_unique', columns: ['user_id', 'game_id'])]
class StatsUserGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'statsUserGames')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private int $gameId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $progress = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $playtime = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastPlayed = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function setGameId(int $gameId): static
    {
        $this->gameId = $gameId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function setProgress(?int $progress): static
    {
        $this->progress = $progress;
        return $this;
    }

    public function getPlaytime(): ?int
    {
        return $this->playtime;
    }

    public function setPlaytime(?int $playtime): static
    {
        $this->playtime = $playtime;
        return $this;
    }

    public function getLastPlayed(): ?\DateTimeInterface
    {
        return $this->lastPlayed;
    }

    public function setLastPlayed(?\DateTimeInterface $lastPlayed): static
    {
        $this->lastPlayed = $lastPlayed;
        return $this;
    }
}
