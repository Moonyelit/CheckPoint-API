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
use App\Controller\Top100GamesAction;
use App\Controller\TopYearGamesAction;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;

#[ApiResource(
    normalizationContext: ['groups' => ['game:read']],
    denormalizationContext: ['groups' => ['game:write']],
    operations: [
        // GET /api/games
        new GetCollection(),

        // GET /api/games/top100?limit=5
        new GetCollection(
            uriTemplate: '/games/top100',
            controller: Top100GamesAction::class,
            read: false,
            paginationEnabled: false,
            extraProperties: [
                'swagger_context' => [
                    'summary' => 'Retourne les jeux du Top 100 d\'IGDB',
                    'parameters' => [
                        [
                            'name' => 'limit',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'default' => 5],
                            'description' => 'Nombre maximal de jeux à renvoyer',
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Liste des jeux du Top 100 IGDB'],
                    ],
                ]
            ]
        ),

        // GET /api/games/top100-year?limit=5
        new GetCollection(
            uriTemplate: '/games/top100-year',
            controller: TopYearGamesAction::class,
            read: false,
            paginationEnabled: false,
            extraProperties: [
                'swagger_context' => [
                    'summary' => 'Retourne les meilleurs jeux de l\'année (365 derniers jours)',
                    'parameters' => [
                        [
                            'name' => 'limit',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'default' => 5],
                            'description' => 'Nombre maximal de jeux à renvoyer',
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Liste des meilleurs jeux de l\'année'],
                    ],
                ]
            ]
        ),

        // GET /api/games/{id}
        new Get(),

        // Opérations CRUD réservées aux admins
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
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
    private ?int $recentHypes = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?int $follows = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:read', 'game:write'])]
    private ?int $totalRatingCount = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['game:read'])]
    private ?\DateTimeImmutable $lastPopularityUpdate = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
        foreach ($this->screenshots as $existing) {
            if ($existing->getImage() === $screenshot->getImage()) {
                return $this;
            }
        }

        $this->screenshots->add($screenshot);
        $screenshot->setGame($this);

        return $this;
    }

    public function removeScreenshot(Screenshot $screenshot): static
    {
        if ($this->screenshots->removeElement($screenshot)) {
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

    public function getRecentHypes(): ?int
    {
        return $this->recentHypes;
    }

    public function setRecentHypes(?int $recentHypes): static
    {
        $this->recentHypes = $recentHypes;

        return $this;
    }

    public function getFollows(): ?int
    {
        return $this->follows;
    }

    public function setFollows(?int $follows): static
    {
        $this->follows = $follows;

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
}