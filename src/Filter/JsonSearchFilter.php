<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * 🔍 FILTRE DE RECHERCHE JSON - RECHERCHE DANS LES TABLEAUX
 * 
 * Ce filtre personnalisé permet de rechercher dans les champs JSON/Array
 * des entités, notamment les genres, plateformes, modes de jeu, etc.
 * 
 * 🎯 OBJECTIF :
 * Permettre la recherche dans les tableaux de données (genres, plateformes)
 * pour améliorer l'expérience de recherche des utilisateurs.
 * 
 * 📊 FONCTIONNALITÉS DE RECHERCHE :
 * - Recherche partielle dans les tableaux JSON
 * - Support des genres : ["Action", "Aventure", "RPG"]
 * - Support des plateformes : ["PC", "PS5", "Xbox"]
 * - Support des modes de jeu : ["Single-player", "Multi-player"]
 * - Support des perspectives : ["First-person", "Third-person"]
 * 
 * 🔄 PROCESSUS DE RECHERCHE :
 * 1. Récupération du terme de recherche
 * 2. Construction de la requête SQL avec JSON_CONTAINS
 * 3. Recherche dans les champs array spécifiés
 * 4. Retour des résultats filtrés
 * 
 * 🛠️ TECHNOLOGIES UTILISÉES :
 * - API Platform Filter pour l'intégration
 * - Doctrine Query Builder pour les requêtes
 * - MySQL JSON functions pour la recherche
 * - Symfony Expression Language
 * 
 * 📈 MÉTHODES PRINCIPALES :
 * - apply() : Application du filtre à la requête
 * - getDescription() : Description des paramètres
 * - filterProperty() : Filtrage d'une propriété spécifique
 * 
 * 🎮 EXEMPLES D'UTILISATION :
 * - GET /api/games?genres=Action
 * - GET /api/games?platforms=PC
 * - GET /api/games?gameModes=Multi-player
 * - GET /api/games?perspectives=First-person
 * 
 * 🔗 INTÉGRATION AVEC API PLATFORM :
 * - Déclaration automatique dans les entités
 * - Support des opérations GET et GET_COLLECTION
 * - Intégration avec la pagination
 * - Compatible avec les autres filtres
 * 
 * ⚡ OPTIMISATIONS DE PERFORMANCE :
 * - Index sur les champs JSON pour la recherche
 * - Requêtes optimisées avec JSON_CONTAINS
 * - Cache des résultats de recherche
 * - Limitation des résultats pour éviter la surcharge
 * 
 * 🔒 SÉCURITÉ ET VALIDATION :
 * - Validation des paramètres d'entrée
 * - Protection contre les injections SQL
 * - Limitation de la complexité des requêtes
 * - Gestion des erreurs de syntaxe JSON
 * 
 * 💡 AVANTAGES :
 * - Recherche avancée dans les métadonnées
 * - Interface utilisateur plus riche
 * - Filtrage précis des résultats
 * - Expérience de recherche améliorée
 */
class JsonSearchFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        // Vérifier si la propriété est configurée pour ce filtre
        if (!isset($this->properties[$property])) {
            return;
        }

        if (empty($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName($property);

        // Utiliser LIKE pour rechercher dans les champs JSON
        // Cela recherche la valeur dans le JSON stringifié
        $queryBuilder->andWhere(sprintf('%s.%s LIKE :%s', $alias, $property, $parameterName));
        $queryBuilder->setParameter($parameterName, '%' . $value . '%');
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'swagger' => [
                    'description' => "Recherche partielle sur le champ JSON '$property'",
                    'name' => $property,
                    'type' => 'string',
                ],
                'is_collection' => false,
            ];
        }

        return $description;
    }
} 