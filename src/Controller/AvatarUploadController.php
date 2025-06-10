<?php

namespace App\Controller;

use App\Service\AvatarSecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;
use Symfony\Component\String\Slugger\SluggerInterface;

class AvatarUploadController extends AbstractController
{
    public function __construct(
        private AvatarSecurityService $avatarSecurityService,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger
    ) {}

    #[Route('/api/upload-avatar', name: 'upload_avatar', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadAvatar(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
            }

            /** @var UploadedFile $avatarFile */
            $avatarFile = $request->files->get('avatar');
            
            if (!$avatarFile) {
                return new JsonResponse(['error' => 'Aucun fichier fourni'], Response::HTTP_BAD_REQUEST);
            }

            // Validation de sécurité du fichier
            $validationResult = $this->validateUploadedFile($avatarFile);
            if ($validationResult !== true) {
                return new JsonResponse(['error' => $validationResult], Response::HTTP_BAD_REQUEST);
            }

            // Générer un nom de fichier sécurisé et unique
            $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

            // Créer le répertoire s'il n'existe pas
            $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/../CheckPoint-Next.JS/public/images/avatars/uploads';
            if (!is_dir($uploadsDirectory)) {
                mkdir($uploadsDirectory, 0755, true);
            }

            // Déplacer le fichier uploadé
            try {
                $avatarFile->move($uploadsDirectory, $newFilename);
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'Erreur lors de l\'upload du fichier'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Redimensionner l'image pour la sécurité et les performances
            $this->resizeImage($uploadsDirectory . '/' . $newFilename, 300, 300);

            // Construire l'URL relative
            $avatarUrl = '/images/avatars/uploads/' . $newFilename;

            // Valider l'URL avec notre service de sécurité
            $validatedUrl = $this->avatarSecurityService->validateAndSanitizeAvatarPath($avatarUrl);

            // Supprimer l'ancien avatar s'il ne s'agit pas de l'avatar par défaut
            $this->deleteOldAvatar($user->getProfileImage());

            // Mettre à jour l'utilisateur
            $user->setProfileImage($validatedUrl);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'avatarUrl' => $validatedUrl,
                'message' => 'Avatar uploadé avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur serveur lors de l\'upload'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateUploadedFile(UploadedFile $file): string|true
    {
        // Vérifier la taille (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            return 'Le fichier est trop volumineux (max 5MB)';
        }

        // Vérifier le type MIME
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return 'Type de fichier non autorisé. Seuls JPG, PNG et WEBP sont acceptés';
        }

        // Vérifier l'extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return 'Extension de fichier non autorisée';
        }

        // Vérifier que c'est bien une image
        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return 'Le fichier n\'est pas une image valide';
        }

        // Vérifier les dimensions minimales
        [$width, $height] = $imageInfo;
        if ($width < 50 || $height < 50) {
            return 'L\'image doit faire au moins 50x50 pixels';
        }

        // Vérifier les dimensions maximales
        if ($width > 2000 || $height > 2000) {
            return 'L\'image ne doit pas dépasser 2000x2000 pixels';
        }

        // Vérifier le nom du fichier pour éviter les attaques
        $filename = $file->getClientOriginalName();
        if (preg_match('/[<>:"\/\\|?*]/', $filename)) {
            return 'Nom de fichier contient des caractères interdits';
        }

        return true;
    }

    private function resizeImage(string $filePath, int $maxWidth, int $maxHeight): void
    {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) return;

        [$originalWidth, $originalHeight, $imageType] = $imageInfo;

        // Calculer les nouvelles dimensions en gardant le ratio
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // Créer une nouvelle image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Préserver la transparence pour PNG
        if ($imageType === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        // Charger l'image source
        $sourceImage = match($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($filePath),
            IMAGETYPE_PNG => imagecreatefrompng($filePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($filePath),
            default => null
        };

        if ($sourceImage === null) return;

        // Redimensionner
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Sauvegarder la nouvelle image
        match($imageType) {
            IMAGETYPE_JPEG => imagejpeg($newImage, $filePath, 85),
            IMAGETYPE_PNG => imagepng($newImage, $filePath, 8),
            IMAGETYPE_WEBP => imagewebp($newImage, $filePath, 85),
        };

        // Nettoyer la mémoire
        imagedestroy($sourceImage);
        imagedestroy($newImage);
    }

    private function deleteOldAvatar(?string $oldAvatarPath): void
    {
        if (!$oldAvatarPath || str_contains($oldAvatarPath, 'DefaultAvatar.JPG')) {
            return; // Ne pas supprimer l'avatar par défaut
        }

        if (str_starts_with($oldAvatarPath, '/images/avatars/uploads/')) {
            $filePath = $this->getParameter('kernel.project_dir') . '/../CheckPoint-Next.JS/public' . $oldAvatarPath;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
} 