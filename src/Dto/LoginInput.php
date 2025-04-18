<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use App\Controller\LoginController;


#[ApiResource(
    collectionOperations: [
        'post' => [
            'path' => '/api/login_check',  
            'controller' => LoginController::class,
            'read' => false,
            'validate' => true,
            'openapi_context' => [
                'summary' => 'User login',
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string'],
                                    'password' => ['type' => 'string']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    itemOperations: []
)]
class LoginInput
{
    #[Assert\NotBlank]
    public string $email;
    
    #[Assert\NotBlank]
    public string $password;
}
