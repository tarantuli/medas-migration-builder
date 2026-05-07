<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure;

use Medas\Core\{
    Attributes\ConfigValue,
    Attributes\Service,
    Interfaces\CacheManager,
    Interfaces\PropertyHandler,
    Interfaces\ServiceManager,
    Interfaces\Type,
    Types\Binary,
    Types\Boolean,
    Types\Collection,
    Types\Integer,
    Types\Relation
};
use Medas\EntityManager\{MetaData, MetaDataManager};
use Medas\StorageManager\ConfigOptions\TypeDefaults\DefaultMaxIntegerValue;
use Medas\StorageManager\Type as StoraType;

#[Service]
readonly class EntityStructureFinder
{
    public function __construct(
        private CacheManager                 $cacheManager,
        private MetaDataManager              $metaDataManager,
        private ParentStoreFinder            $parentStoreFinder,
        private ServiceManager               $serviceManager,
        private TypeHandlerFinder            $typeHandlerFinder,
        private TypeHandlers\EnumHandler     $enumHandler,
        private TypeHandlers\RelationHandler $relationHandler,

        #[ConfigValue(DefaultMaxIntegerValue::class)]
        private int                          $defaultMaxIntegerValue,
    )
    {
    }

    public function find(string $className): Blueprint
    {
        return $this->cacheManager->get()->get(
            [__CLASS__, $className],
            fn() => $this->compile($className)
        );
    }

    private function compile(string $className): Blueprint
    {
        $job = new EntityStructureFinder\Job(
            $this->metaDataManager->get($className),
            new Blueprint()
        );

        $this->findName($job);
        $this->findInheritance($job);
        $this->findFields($job);
        $this->findPrimaryKey($job);
        $this->findKeys($job);
        $this->findForeignKeys($job);

        return $job->blueprint;
    }

    private function findName(EntityStructureFinder\Job $job): void
    {
        if ($job->metaData->entity->store === null) {
            return;
        }

        $job->blueprint->name = $job->metaData->entity->store;
        $job->blueprint->parent = $job->metaData->inheritance->parent;
    }

    private function findInheritance(EntityStructureFinder\Job $job): void
    {
        if ($job->blueprint->storeOriginalClass = $job->metaData->inheritance->storeOriginalClass) {
            $job->blueprint->storeRequestingOriginalClassStorage
                = $this->metaDataManager->get($job->metaData->inheritance->sharedParentClass)->entity->store;
        }
    }

    private function findFields(EntityStructureFinder\Job $job): void
    {
        $parents = $this->parentStoreFinder->find($job->metaData);

        foreach ($job->metaData->properties as $property) {
            $job->blueprint->addField($this->fieldFromProperty($property, $parents));
        }
    }

    private function findPrimaryKey(EntityStructureFinder\Job $job): void
    {
        $index = new Blueprint\Index([], true);

        $index->addField($job->blueprint->fieldByName($job->metaData->idProperty->name));

        $job->blueprint->addIndex($index);
    }

    private function findKeys(EntityStructureFinder\Job $job): void
    {
        foreach ($job->metaData->properties as $property) {
            if (!$property->isUnique || $property->isIndex) {
                continue;
            }

            $this->addIndex($job, [$property->name], $property->isUnique);
        }

        foreach ($job->metaData->uniquePropertySets as $propertyNames) {
            $this->addIndex($job, $propertyNames, true);
        }

        foreach ($job->metaData->compoundIndexes as $propertyNames) {
            $this->addIndex($job, $propertyNames, false);
        }
    }

    private function addIndex(EntityStructureFinder\Job $job, mixed $propertyNames, bool $isUnique): void
    {
        $index = new Blueprint\Index([], false, $isUnique);

        foreach ($propertyNames as $propertyName) {
            $index->addField($job->blueprint->fieldByName($propertyName));
        }

        $job->blueprint->addIndex($index);
    }

    private function findForeignKeys(EntityStructureFinder\Job $job): void
    {
        foreach ($job->metaData->properties as $property) {
            $handler = $this->typeHandlerFinder->for($property->type);

            if ($foreignKey = $handler->foreignKey($property)) {
                $job->blueprint->addForeignKey($foreignKey);
            }
        }
    }

    public function fieldFromProperty(
        MetaData\Property $property,
        ParentStores|null $parentStores = null
    ): Blueprint\Field
    {
        $field = new Blueprint\Field($property->name, StoraType::Text);

        if ($parentStores) {
            $field->store = $parentStores->getStore($property->reflection->getDeclaringClass()->name);
        }

        $typeHandler = $this->typeHandlerFinder->for($property->type);
        $field->type = $typeHandler->fieldType($property);

        if ($field->type === StoraType::Collection) {
            $this->handleCollectionField($property, $field);
        }

        if (!$property->isGeneratedValue && $property->isNullable) {
            $field->isNullable = true;
        }

        if ($property->isUnique) {
            $field->isUnique = true;
        }

        if ($property->isIndex) {
            $field->isIndex = true;
        }

        if ($property->isGeneratedValue) {
            $field->isGenerated = true;
        }

        if ($property->isCreationTimestamp) {
            $field->isCreationTimestamp = true;
        }

        if ($property->isModificationTimestamp) {
            $field->isModificationTimestamp = true;
        }

        if ($property->hasDefault) {
            $field->hasDefault = true;
            $field->default = $property->default;

            if ($class = $property->handler) {
                // This property has been assigned a handler, let it serialize the value
                /** @var PropertyHandler $propertyHandler */
                $propertyHandler = $this->serviceManager->resolve($class);
                $field->default = $propertyHandler->serialize($field->default);
            }
        }

        $type = $property->type;

        if ($type instanceof Relation) {
            $type = $this->getRelationType($type);
        }

        if ($type instanceof Integer) {
            $field->minValue = $type->minValue;
            $field->maxValue = $type->maxValue ?? $this->defaultMaxIntegerValue;
        }

        if ($type instanceof Binary) {
            $field->minLength = $type->minLength;
            $field->maxLength = $type->maxLength;
        }

        if ($type instanceof Boolean) {
            $field->minValue = 0;
            $field->maxValue = 1;
        }

        return $field;
    }

    private function handleCollectionField(MetaData\Property $property, Blueprint\Field $field): void
    {
        /** @var Collection $collectionType */
        $collectionType = $property->type;
        $collectionTypeHandler = $this->typeHandlerFinder->forString($collectionType->contentType);

        if (!$collectionTypeHandler instanceof TypeHandlers\RelationHandler) {
            return;
        }

        $referencedMetaData = $this->metaDataManager->get($collectionType->contentType);
        $collectionProperty = $referencedMetaData->idProperty;
        $field->collectionField = $this->fieldFromProperty($collectionProperty);
        $field->collectionStore = $referencedMetaData->entity->store;
    }

    private function getRelationType(Relation $type): Type
    {
        if (enum_exists($type->entity)) {
            return $this->enumHandler->getPseudoType($type->entity);
        }

        // Use the type of the id property of the related entity
        $type = $this->relationHandler->getIdProperty($type->entity)->type;

        if ($type instanceof Relation) {
            return $this->getRelationType($type);
        }

        return $type;
    }
}
