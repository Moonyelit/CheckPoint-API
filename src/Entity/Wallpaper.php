<?php

namespace App\Entity;

use App\Repository\WallpaperRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Game;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: WallpaperRepository::class)]
class Wallpaper
{
    // Propriétés de l'entité
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_wallpaper:read'])]
    private ?int $id = null; // Identifiant unique de l'entité

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user_wallpaper:read'])]
    private ?string $image = null; // URL ou chemin de l'image associée au wallpaper

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'wallpapers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_wallpaper:read'])]
    private ?Game $game = null; // Relation ManyToOne avec l'entité Game

    // Section des getters et setters

    /**
     * Récupère l'identifiant du wallpaper.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Récupère l'image associée au wallpaper.
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Définit l'image associée au wallpaper.
     */
    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Récupère le jeu associé au wallpaper.
     */
    public function getGame(): ?Game
    {
        return $this->game;
    }

    /**
     * Définit le jeu associé au wallpaper.
     */
    public function setGame(?Game $game): static
    {
        $this->game = $game;
        return $this;
    }
}
