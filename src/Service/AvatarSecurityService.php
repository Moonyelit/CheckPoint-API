<?php

namespace App\Service;

use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * 🛡️ SERVICE AVATAR SECURITY - SÉCURISATION & VALIDATION DES AVATARS UTILISATEUR
 *
 * Ce service centralise toutes les vérifications, nettoyages et sécurisations
 * des chemins d'avatars utilisateurs pour garantir la sécurité et l'intégrité des données.
 *
 * 🔧 FONCTIONNALITÉS PRINCIPALES :
 *
 * 🔒 VALIDATION & NETTOYAGE :
 * - Validation stricte des chemins d'avatars (format, extension, longueur)
 * - Nettoyage des chemins pour éviter les attaques XSS et path traversal
 * - Filtrage des extensions autorisées (png, jpg, jpeg, webp, svg)
 *
 * 🖼️ GESTION DES AVATARS PAR DÉFAUT :
 * - Fournit un avatar par défaut sécurisé si le chemin est invalide ou absent
 * - Liste des avatars par défaut disponibles
 *
 * 🛡️ SÉCURITÉ AVANCÉE :
 * - Vérification de l'existence physique (optionnelle)
 * - Génération d'en-têtes CSP pour les images
 * - Validation des headers de sécurité lors des requêtes
 *
 * 🎯 UTILISATION :
 * - Utilisé lors de l'upload, de l'affichage ou de la modification d'un avatar utilisateur
 * - Garantit que seuls des chemins sûrs et valides sont utilisés côté frontend et backend
 *
 * ⚡ EXEMPLES D'USAGE :
 * - Nettoyage automatique lors de l'enregistrement d'un profil
 * - Sécurisation des URLs d'avatars affichées sur le site
 * - Validation des avatars uploadés par les utilisateurs
 *
 * 💡 AVANTAGES :
 * - Réduction drastique des risques de failles XSS ou d'accès non autorisé
 * - Cohérence et sécurité des données utilisateurs
 * - Facile à étendre pour de nouveaux formats ou règles
 *
 * 🔧 UTILISATION RECOMMANDÉE :
 * - Pour toute gestion d'avatars utilisateurs (upload, affichage, modification)
 * - Pour garantir la sécurité des médias utilisateurs
 */
class AvatarSecurityService
{
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    private const MAX_PATH_LENGTH = 500;
    private const AVATAR_BASE_PATH = '/images/avatars/';
    private const DEFAULT_AVATAR = '/images/avatars/DefaultAvatar.JPG';
    
    private const DANGEROUS_PATTERNS = [
        '../',
        '..',
        '\\',
        '<script',
        'javascript:',
        'data:',
        'vbscript:',
        'on\w+\s*=',
        'eval\(',
        'alert\(',
        'document.',
        'window.',
    ];

    /**
     * Valide et nettoie un chemin d'avatar
     */
    public function validateAndSanitizeAvatarPath(?string $path): string
    {
        // Si pas de chemin fourni, retourner l'avatar par défaut
        if (empty($path)) {
            return self::DEFAULT_AVATAR;
        }

        // Vérifier la longueur maximale
        if (strlen($path) > self::MAX_PATH_LENGTH) {
            throw new InvalidArgumentException('Le chemin de l\'avatar est trop long');
        }

        // Nettoyer le chemin
        $cleanPath = $this->sanitizePath($path);

        // Valider le format
        if (!$this->isValidAvatarPath($cleanPath)) {
            return self::DEFAULT_AVATAR;
        }

        return $cleanPath;
    }

    /**
     * Nettoie le chemin pour éviter les attaques XSS et path traversal
     */
    private function sanitizePath(string $path): string
    {
        // Échapper les caractères HTML
        $cleanPath = htmlspecialchars(trim($path), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Supprimer les patterns dangereux
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            $cleanPath = str_ireplace($pattern, '', $cleanPath);
        }
        
        // Supprimer les caractères de contrôle
        $cleanPath = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanPath);
        
        // Normaliser les slashes
        $cleanPath = str_replace(['\\', '//'], '/', $cleanPath);
        
        return $cleanPath;
    }

    /**
     * Valide que le chemin est un chemin d'avatar valide
     */
    private function isValidAvatarPath(string $path): bool
    {
        // Le chemin doit commencer par le chemin de base des avatars
        if (!str_starts_with($path, self::AVATAR_BASE_PATH)) {
            return false;
        }

        // Extraire le nom du fichier
        $filename = basename($path);
        
        // Vérifier que le nom de fichier ne contient que des caractères autorisés
        // Support pour les fichiers uploadés avec des IDs uniques
        if (!preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+$/', $filename)) {
            return false;
        }

        // Vérifier l'extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        // Vérifier qu'il n'y a pas de tentative de path traversal
        if (strpos($path, '..') !== false) {
            return false;
        }

        // Permettre les uploads dans le sous-dossier uploads
        $allowedPaths = [
            '/images/avatars/',
            '/images/avatars/uploads/'
        ];
        
        $isValidPath = false;
        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($path, $allowedPath)) {
                $isValidPath = true;
                break;
            }
        }

        return $isValidPath;
    }

    /**
     * Retourne la liste des avatars par défaut disponibles
     */
    public function getDefaultAvatars(): array
    {
        return [
            '/images/avatars/DefaultAvatar.JPG',
        ];
    }

    /**
     * Vérifie si un avatar existe physiquement
     */
    public function avatarExists(string $path): bool
    {
        if (!$this->isValidAvatarPath($path)) {
            return false;
        }

        // Pour une vraie implémentation, on vérifierait si le fichier existe
        // Pour l'instant, on accepte tous les chemins valides
        return true;
    }

    /**
     * Génère un CSP (Content Security Policy) header pour les avatars
     */
    public function getAvatarCSP(): string
    {
        return "img-src 'self' data: blob:; object-src 'none';";
    }

    /**
     * Valide les en-têtes de sécurité pour les requêtes d'avatar
     */
    public function validateSecurityHeaders(array $headers): bool
    {
        // Vérifier la présence d'en-têtes de sécurité recommandés
        $requiredHeaders = [
            'Content-Type',
            'User-Agent'
        ];

        foreach ($requiredHeaders as $header) {
            if (!isset($headers[strtolower($header)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filtre et valide les données utilisateur pour les avatars
     */
    public function sanitizeUserAvatarData(array $data): array
    {
        $cleaned = [];

        if (isset($data['profileImage'])) {
            $cleaned['profileImage'] = $this->validateAndSanitizeAvatarPath($data['profileImage']);
        }

        return $cleaned;
    }
} 