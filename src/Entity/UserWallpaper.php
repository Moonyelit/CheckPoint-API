<?php

namespace App\Entity;

use App\Repository\UserWallpaperRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['user_wallpaper:read']],
    denormalizationContext: ['groups' => ['user_wallpaper:write']],
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            securityMessage: "Seuls les utilisateurs connectés peuvent voir leurs wallpapers"
        ),
        new Get(
            security: "is_granted('ROLE_USER') and object.getUser() == user",
            securityMessage: "Vous ne pouvez voir que vos propres wallpapers"
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            securityMessage: "Seuls les utilisateurs connectés peuvent ajouter des wallpapers"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object.getUser() == user",
            securityMessage: "Vous ne pouvez supprimer que vos propres wallpapers"
        )
    ]
)]
#[ORM\Entity(repositoryClass: UserWallpaperRepository::class)]
#[ORM\Table(name: 'user_wallpaper')]
#[ORM\UniqueConstraint(name: 'user_wallpaper_unique', columns: ['user_id', 'wallpaper_id'])]
class UserWallpaper
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_wallpaper:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_wallpaper:read', 'user_wallpaper:write'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Wallpaper::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_wallpaper:read', 'user_wallpaper:write'])]
    private ?Wallpaper $wallpaper = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user_wallpaper:read'])]
    private ?\DateTimeImmutable $selectedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['user_wallpaper:read', 'user_wallpaper:write'])]
    private bool $isActive = true;

    public function __construct()
    {
        $this->selectedAt = new \DateTimeImmutable();
        $this->isActive = true;
    }

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

    public function getWallpaper(): ?Wallpaper
    {
        return $this->wallpaper;
    }

    public function setWallpaper(?Wallpaper $wallpaper): static
    {
        $this->wallpaper = $wallpaper;
        return $this;
    }

    public function getSelectedAt(): ?\DateTimeImmutable
    {
        return $this->selectedAt;
    }

    public function setSelectedAt(\DateTimeImmutable $selectedAt): static
    {
        $this->selectedAt = $selectedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
} 