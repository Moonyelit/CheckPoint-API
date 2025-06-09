<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');
        $email = $request->query->get('email');

        if (!$token || !$email) {
            return $this->redirectToRoute('verification_error', [
                'error' => 'token-invalid'
            ]);
        }

        // Trouver l'utilisateur par email
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->redirectToRoute('verification_error', [
                'error' => 'user-not-found'
            ]);
        }

        // Pour cet exemple, on accepte tous les tokens (à améliorer en production)
        // En production, vous devriez vérifier le token en base de données
        
        // Marquer l'email comme vérifié
        $user->setEmailVerified(true);
        $this->entityManager->flush();

        // Rediriger vers le frontend Next.js avec succès
        $frontendUrl = $_ENV['NEXT_PUBLIC_BASE_URL'] ?? 'http://localhost:3000';
        return $this->redirect(
            $frontendUrl . '/inscription?verified=true&email=' . urlencode($email)
        );
    }

    #[Route('/api/verify-email/error', name: 'verification_error', methods: ['GET'])]
    public function verificationError(Request $request): Response
    {
        $error = $request->query->get('error', 'unknown');
        
        $frontendUrl = $_ENV['NEXT_PUBLIC_BASE_URL'] ?? 'http://localhost:3000';
        return $this->redirect(
            $frontendUrl . '/inscription?error=' . $error
        );
    }
} 