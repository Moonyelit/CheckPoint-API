<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Wallpaper;
use App\Entity\Screenshot;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Filter\JsonSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;

#[ApiResource(
    normalizationContext: ['groups' => ['game:read']],
    denormalizationContext: ['groups' => ['game:write']],
    operations: [
        // GET /api/games
        new GetCollection(),

        // Opérations CRUD réservées aux admins
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'partial',
    'developer' => 'partial'
])]
#[ApiFilter(JsonSearchFilter::class, properties: [
    'genres' => 'partial',
    'platforms' => 'partial', 
    'gameModes' => 'partial',
    'perspectives' => 'partial'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'title' => 'ASC',
    'totalRating' => 'DESC',
    'totalRatingCount' => 'DESC',
    'releaseDate' => 'DESC'
])]
#[ApiFilter(DateFilter::class, properties: [
    'releaseDate' => DateFilter::EXCLUDE_NULL
])]
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['game:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['game:read', 'game:write'])]
    private ?int $igdbId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?\DateTimeInterface $releaseDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $developer = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?array $platforms = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?array $genres = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?array $gameModes = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?array $perspectives = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?float $totalRating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $summary = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $coverUrl = null;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Wallpaper::class, cascade: ['persist', 'remove'])]
    #[Groups(['game:read', 'game:write'])]
    private Collection $wallpapers;

    /**
     * @var Collection<int, Screenshot>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Screenshot::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['game:read', 'game:write'])]
    private Collection $screenshots;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['game:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['game:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?int $totalRatingCount = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['game:read'])]
    private ?\DateTimeImmutable $lastPopularityUpdate = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $publisher = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?array $alternativeTitles = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?string $ageRating = null;

    // Nouveaux champs pour compter les médias
    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?int $screenshotsCount = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?int $artworksCount = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?int $videosCount = null;

    /**
     * @var Collection<int, Artwork>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Artwork::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['game:read', 'game:write'])]
    private Collection $artworks;

    /**
     * @var Collection<int, Video>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Video::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['game:read', 'game:write'])]
    private Collection $videos;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->wallpapers = new ArrayCollection();
        $this->screenshots = new ArrayCollection();
        $this->artworks = new ArrayCollection();
        $this->videos = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

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

    public function getGameModes(): ?array
    {
        return $this->gameModes;
    }

    public function setGameModes(?array $gameModes): static
    {
        $this->gameModes = $gameModes;

        return $this;
    }

    public function getPerspectives(): ?array
    {
        return $this->perspectives;
    }

    public function setPerspectives(?array $perspectives): static
    {
        $this->perspectives = $perspectives;

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

    /**
     * Récupère l'URL de la couverture du jeu avec nettoyage automatique
     */
    #[Groups(['game:read'])]
    public function getCoverUrl(): ?string
    {
        if (!$this->coverUrl) {
            return null;
        }
        
        // Décoder l'URL si elle est encodée
        $decodedUrl = urldecode($this->coverUrl);
        
        // Nettoyer les protocoles dupliqués (https://https:// ou http://https://)
        $decodedUrl = preg_replace('/^https?:\/\/https?:\/\/?/', 'https://', $decodedUrl);
        $decodedUrl = preg_replace('/^https?:\/\/http:\/\/?/', 'https://', $decodedUrl);
        
        // S'assurer que l'URL a le bon format
        if (strpos($decodedUrl, '//') === 0) {
            $decodedUrl = 'https:' . $decodedUrl;
        } elseif (!preg_match('/^https?:\/\//', $decodedUrl)) {
            $decodedUrl = 'https://' . $decodedUrl;
        }
        
        return $decodedUrl;
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
        foreach ($this->screenshots as $existing) {
            if ($existing->getImage() === $screenshot->getImage()) {
                return $this;
            }
        }

        $this->screenshots->add($screenshot);
        $screenshot->setGame($this);
        $this->screenshotsCount = $this->screenshots->count();

        return $this;
    }

    public function removeScreenshot(Screenshot $screenshot): static
    {
        if ($this->screenshots->removeElement($screenshot)) {
            if ($screenshot->getGame() === $this) {
                $screenshot->setGame(null);
            }
        }
        $this->screenshotsCount = $this->screenshots->count();
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

    public function getTotalRatingCount(): ?int
    {
        return $this->totalRatingCount;
    }

    public function setTotalRatingCount(?int $totalRatingCount): static
    {
        $this->totalRatingCount = $totalRatingCount;

        return $this;
    }

    public function getLastPopularityUpdate(): ?\DateTimeImmutable
    {
        return $this->lastPopularityUpdate;
    }

    public function setLastPopularityUpdate(?\DateTimeImmutable $lastPopularityUpdate): static
    {
        $this->lastPopularityUpdate = $lastPopularityUpdate;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getAlternativeTitles(): ?array
    {
        return $this->alternativeTitles;
    }

    public function setAlternativeTitles(?array $alternativeTitles): static
    {
        $this->alternativeTitles = $alternativeTitles;

        return $this;
    }

    public function getAgeRating(): ?string
    {
        return $this->ageRating;
    }

    public function setAgeRating(?string $ageRating): static
    {
        $this->ageRating = $ageRating;

        return $this;
    }

    public function getScreenshotsCount(): ?int
    {
        return $this->screenshotsCount;
    }

    public function setScreenshotsCount(?int $screenshotsCount): static
    {
        $this->screenshotsCount = $screenshotsCount;

        return $this;
    }

    public function getArtworksCount(): ?int
    {
        return $this->artworksCount;
    }

    public function setArtworksCount(?int $artworksCount): static
    {
        $this->artworksCount = $artworksCount;

        return $this;
    }

    public function getVideosCount(): ?int
    {
        return $this->videosCount;
    }

    public function setVideosCount(?int $videosCount): static
    {
        $this->videosCount = $videosCount;

        return $this;
    }

    /**
     * @return Collection<int, Artwork>
     */
    public function getArtworks(): Collection
    {
        return $this->artworks;
    }

    public function addArtwork(Artwork $artwork): static
    {
        if (!$this->artworks->contains($artwork)) {
            $this->artworks->add($artwork);
            $artwork->setGame($this);
        }
        return $this;
    }

    public function removeArtwork(Artwork $artwork): static
    {
        if ($this->artworks->removeElement($artwork)) {
            if ($artwork->getGame() === $this) {
                $artwork->setGame(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setGame($this);
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getGame() === $this) {
                $video->setGame(null);
            }
        }
        return $this;
    }

    /**
     * Récupère le premier screenshot du jeu pour l'utiliser comme image de fond.
     * 
     * @return string|null L'URL du premier screenshot ou null si aucun screenshot n'est disponible
     */
    #[Groups(['game:read'])]
    public function getFirstScreenshotUrl(): ?string
    {
        $screenshots = $this->getScreenshots();
        if ($screenshots->isEmpty()) {
            return null;
        }
        
        $firstScreenshot = $screenshots->first();
        $imageUrl = $firstScreenshot->getImage();
        
        if (!$imageUrl) {
            return null;
        }
        
        // Décoder l'URL si elle est encodée
        $decodedUrl = urldecode($imageUrl);
        
        // S'assurer que l'URL a le bon format
        if (strpos($decodedUrl, '//') === 0) {
            $decodedUrl = 'https:' . $decodedUrl;
        } elseif (!preg_match('/^https?:\/\//', $decodedUrl)) {
            $decodedUrl = 'https://' . $decodedUrl;
        }
        
        return $decodedUrl;
    }

    /**
     * Récupère le premier screenshot du jeu pour l'utiliser dans le header.
     * 
     * @return Screenshot|null Le premier screenshot ou null si aucun screenshot n'est disponible
     */
    #[Groups(['game:read'])]
    public function getFirstScreenshot(): ?Screenshot
    {
        $screenshots = $this->getScreenshots();
        if ($screenshots->isEmpty()) {
            return null;
        }
        
        return $screenshots->first();
    }
}