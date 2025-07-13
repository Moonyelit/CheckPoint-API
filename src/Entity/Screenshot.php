<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Repository\ScreenshotRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Game;

#[ApiResource(
    normalizationContext: ['groups' => ['screenshot:read']],
    denormalizationContext: ['groups' => ['screenshot:write']],
    operations: [
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ]
)]
#[ORM\Entity(repositoryClass: ScreenshotRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Screenshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['screenshot:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['screenshot:read', 'screenshot:write', 'game:read'])]
    private ?string $image = null;

    #[ORM\ManyToOne(inversedBy: 'screenshots')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['screenshot:read', 'screenshot:write'])]
    private ?Game $game = null;

    #[ORM\Column]
    #[Groups(['screenshot:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['screenshot:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
        if (!$this->image) {
            return null;
        }
        
        // Décoder l'URL si elle est encodée
        $decodedUrl = urldecode($this->image);
        
        // S'assurer que l'URL a le bon format
        if (strpos($decodedUrl, '//') === 0) {
            $decodedUrl = 'https:' . $decodedUrl;
        } elseif (!preg_match('/^https?:\/\//', $decodedUrl)) {
            $decodedUrl = 'https://' . $decodedUrl;
        }
        
        return $decodedUrl;
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
