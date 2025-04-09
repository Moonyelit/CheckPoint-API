<?php

namespace App\Entity;

use App\Repository\ScreenshotRepository;
use Doctrine\ORM\Mapping as ORM;

use App\Entity\Game;

/**
 * Classe représentant une entité Screenshot dans la base de données.
 * @ORM\Entity(repositoryClass=ScreenshotRepository::class)
 */
#[ORM\Entity(repositoryClass: ScreenshotRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Screenshot
{
    // Identifiant unique de l'entité (clé primaire).
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Chemin ou nom de l'image associée au screenshot.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    // Relation ManyToOne avec l'entité Game (un jeu peut avoir plusieurs screenshots).
    #[ORM\ManyToOne(inversedBy: 'screenshots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable(); // Initialisation de createdAt
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
