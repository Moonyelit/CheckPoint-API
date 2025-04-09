<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Wallpaper;
use App\Entity\Screenshot;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $igdbId = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $releaseDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $developer = null;

    #[ORM\Column(nullable: true)]
    private ?array $platforms = null;

    #[ORM\Column(nullable: true)]
    private ?array $genres = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalRating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverUrl = null;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Wallpaper::class, cascade: ['persist', 'remove'])]
    private Collection $wallpapers;

    /**
     * @var Collection<int, Screenshot>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Screenshot::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $screenshots;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;    

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable(); // Initialisation de createdAt
        $this->wallpapers = new ArrayCollection();
        $this->screenshots = new ArrayCollection();
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

    public function getIgdbId(): ?int
    {
        return $this->igdbId;
    }

    public function setIgdbId(int $igdbId): static
    {
        $this->igdbId = $igdbId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeInterface $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getDeveloper(): ?string
    {
        return $this->developer;
    }

    public function setDeveloper(?string $developer): static
    {
        $this->developer = $developer;

        return $this;
    }

    public function getPlatforms(): ?array
    {
        return $this->platforms;
    }

    public function setPlatforms(?array $platforms): static
    {
        $this->platforms = $platforms;

        return $this;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function setGenres(?array $genres): static
    {
        $this->genres = $genres;

        return $this;
    }

    public function getTotalRating(): ?float
    {
        return $this->totalRating;
    }

    public function setTotalRating(?float $totalRating): static
    {
        $this->totalRating = $totalRating;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): static
    {
        $this->coverUrl = $coverUrl;

        return $this;
    }

    /**
     * @return Collection<int, Wallpaper>
     */
    public function getWallpapers(): Collection
    {
        return $this->wallpapers;
    }

    public function addWallpaper(Wallpaper $wallpaper): static
    {
        if (!$this->wallpapers->contains($wallpaper)) {
            $this->wallpapers->add($wallpaper);
            $wallpaper->setGame($this);
        }

        return $this;
    }

    public function removeWallpaper(Wallpaper $wallpaper): static
    {
        if ($this->wallpapers->removeElement($wallpaper)) {
            if ($wallpaper->getGame() === $this) {
                $wallpaper->setGame(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Screenshot>
     */
    public function getScreenshots(): Collection
    {
        return $this->screenshots;
    }

    public function addScreenshot(Screenshot $screenshot): static
    {
        // Vérifie s'il y a déjà un screenshot avec la même image (ou critère pertinent)
        foreach ($this->screenshots as $existing) {
            if ($existing->getImage() === $screenshot->getImage()) {
                return $this; // Déjà présent, on ne l'ajoute pas
            }
        }

        $this->screenshots->add($screenshot);
        $screenshot->setGame($this);

        return $this;
    }

    public function removeScreenshot(Screenshot $screenshot): static
    {
        if ($this->screenshots->removeElement($screenshot)) {
            // set the owning side to null (unless already changed)
            if ($screenshot->getGame() === $this) {
                $screenshot->setGame(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
