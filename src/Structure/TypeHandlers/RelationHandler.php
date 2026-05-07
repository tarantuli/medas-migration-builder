<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\TypeHandlers;

use Medas\Core\{Attributes\Service, Interfaces\ServiceManager, Types\Relation};
use Medas\EntityManager\{MetaData\Property, MetaDataManager};
use Medas\MigrationBuilder\Structure\{Blueprint\ForeignKey, TypeHandlerFinder};
use Medas\StorageManager\Type;

#[Service]
readonly class RelationHandler extends BaseHandler
{
    public function __construct(
        private EnumHandler     $enumHandler,
        private MetaDataManager $metaDataManager,
        private ServiceManager  $serviceManager,
    )
    {
    }

    public function fieldType(Property|null $property): Type
    {
        /** @var Relation $type */
        $type = $property->type;

        return match (true) {
            enum_exists($type->entity) => $this->enumHandler->getBlueprintType($type->entity),
            default => $this->getEntityType($type->entity),
        };
    }

    private function getEntityType(string $entity): Type
    {
        $idProperty = $this->getIdProperty($entity);

        // We can't inject it in the constructor due to circular dependencies
        $typeHandlerFinder = $this->serviceManager->resolve(TypeHandlerFinder::class);

        return $typeHandlerFinder->for($idProperty->type)->fieldType($idProperty);
    }

    public function getIdProperty(string $entity): Property|null
    {
        return $this->metaDataManager->get($entity)->idProperty;
    }

    public function foreignKey(Property $property): ForeignKey|null
    {
        /** @var Relation $type */
        $type = $property->type;

        if (enum_exists($type->entity)) {
            return null;
        }

        return new ForeignKey(
            $property->name,
            $this->metaDataManager->get($type->entity)->entity->store,
            $this->getIdProperty($type->entity)->name,
            $property->onDelete,
            $property->onUpdate,
        );
    }
}
