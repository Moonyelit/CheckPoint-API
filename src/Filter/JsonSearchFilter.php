<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

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