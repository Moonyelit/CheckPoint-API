<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use App\State\UserRegisterProcessor;
use App\State\MeProvider;
use App\State\UserUpdateProcessor;
use App\Entity\StatsUserGame;

#[ApiResource(
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
    operations: [
        new Post(
            uriTemplate: '/register',
            denormalizationContext: ['groups' => ['user:write']],
            validationContext: ['groups' => ['Default', 'user:create']],
            security: "is_granted('PUBLIC_ACCESS')",
            processor: UserRegisterProcessor::class
        ),
        new Get(
            uriTemplate: '/me',
            security: "is_granted('ROLE_USER')",
            provider: MeProvider::class,
            normalizationContext: ['groups' => ['user:read']],
            securityMessage: "Vous devez être connecté pour accéder à cette ressource",
            uriVariables: []
        ),
        new Patch(
            uriTemplate: '/me',
            denormalizationContext: ['groups' => ['user:update']],
            normalizationContext: ['groups' => ['user:read']],
            validationContext: ['groups' => ['Default', 'user:update']],
            security: "is_granted('ROLE_USER')",
            processor: UserUpdateProcessor::class,
            securityMessage: "Vous devez être connecté pour modifier votre profil"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['user:update']],
            normalizationContext: ['groups' => ['user:read']],
            validationContext: ['groups' => ['Default', 'user:update']],
            security: "is_granted('ROLE_USER') and object == user",
            processor: UserUpdateProcessor::class,
            securityMessage: "Vous ne pouvez modifier que votre propre compte"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object == user",
            securityMessage : "Vous ne pouvez supprimer que votre propre compte"
        )
    ]
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'Le pseudo est obligatoire', groups: ['user:create'])]
    #[Assert\Length(
        min: 3,
        max: 15,
        minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères',
        groups: ['user:create']
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_-]+$/',
        message: 'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores',
        groups: ['user:create']
    )]
    private ?string $pseudo = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'L\'email est obligatoire', groups: ['user:create'])]
    #[Assert\Email(message: 'L\'email n\'est pas valide', groups: ['user:create'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire', groups: ['user:create'])]
    #[Assert\Length(
        min: 12,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères',
        groups: ['user:create']
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/',
        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial',
        groups: ['user:create']
    )]
    #[Assert\NotCompromisedPassword(message: 'Ce mot de passe a été compromis dans une fuite de données. Veuillez en choisir un autre.', groups: ['user:create'])]
    private ?string $password = null;

    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'La confirmation du mot de passe est obligatoire', groups: ['user:create'])]
    private ?string $confirmPassword = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read', 'user:update'])]
    private bool $emailVerified = false;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['user:read', 'user:update'])]
    #[Assert\Regex(
        pattern: '/^\/images\/avatars\/(uploads\/)?[a-zA-Z0-9_.-]+\.(png|jpg|jpeg|webp|svg|JPG)$/i',
        message: 'L\'image de profil doit être un chemin valide vers /images/avatars/ avec une extension autorisée',
        groups: ['user:update']
    )]
    private ?string $profileImage = '/images/avatars/DefaultAvatar.JPG';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read', 'user:update'])]
    private bool $tutorialCompleted = false;

    /**
     * @var Collection<int, StatsUserGame>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: StatsUserGame::class, orphanRemoval: true)]
    private Collection $statsUserGames;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?StatsUser $statsUser = null;

    public function __construct()
    {
        $this->statsUserGames = new ArrayCollection();
        $this->profileImage = '/images/avatars/DefaultAvatar.JPG';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getConfirmPassword(): ?string
    {
        return $this->confirmPassword;
    }

    public function setConfirmPassword(?string $confirmPassword): static
    {
        $this->confirmPassword = $confirmPassword;
        return $this;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): static
    {
        // La validation sera faite par le AvatarSecurityService dans le processor
        $this->profileImage = $profileImage ?? '/images/avatars/DefaultAvatar.JPG';
        return $this;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // Efface les données sensibles temporaires si nécessaire
    }

    /**
     * @return Collection<int, StatsUserGame>
     */
    public function getStatsUserGames(): Collection
    {
        return $this->statsUserGames;
    }

    public function addStatsUserGame(StatsUserGame $statsUserGame): static
    {
        if (!$this->statsUserGames->contains($statsUserGame)) {
            $this->statsUserGames->add($statsUserGame);
            $statsUserGame->setUser($this);
        }
        return $this;
    }

    public function removeStatsUserGame(StatsUserGame $statsUserGame): static
    {
        if ($this->statsUserGames->removeElement($statsUserGame)) {
            if ($statsUserGame->getUser() === $this) {
                $statsUserGame->setUser(null);
            }
        }
        return $this;
    }

    public function getStatsUser(): ?StatsUser
    {
        return $this->statsUser;
    }

    public function setStatsUser(StatsUser $statsUser): static
    {
        // set the owning side of the relation if necessary
        if ($statsUser->getUser() !== $this) {
            $statsUser->setUser($this);
        }

        $this->statsUser = $statsUser;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): self
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    public function isTutorialCompleted(): bool
    {
        return $this->tutorialCompleted;
    }

    public function setTutorialCompleted(bool $tutorialCompleted): self
    {
        $this->tutorialCompleted = $tutorialCompleted;
        return $this;
    }
}
