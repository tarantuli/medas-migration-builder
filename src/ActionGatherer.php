<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{Attributes\Service, Interfaces\FileLoader};
use Medas\EntityManager\Attributes\Entity;
use Medas\StorageManager\{StorageManager, UnitOfWork\ActionSet};

#[Service]
readonly class ActionGatherer
{
    public function __construct(
        private BuilderResolver                           $builderResolver,
        private FileLoader                                $fileLoader,
        private MigrationFactory\StoredEntityDeterminator $storedEntityDeterminator,
        private StorageManager                            $storageManager,
        private Structure\EntityBlueprintBuilder          $entityBlueprintBuilder,
    )
    {
    }

    /**
     * Loads all PHP files in the given directories and returns all stored entity
     * classes found in them, keyed by class name.
     *
     * @return array<string, Entity>
     */
    public function scan(array $directories): array
    {
        foreach ($directories as &$directory) {
            $directory = realpath($directory);

            $this->fileLoader->load($directory);
        }

        $entities = [];

        foreach (get_declared_classes() as $className) {
            if ($entity = $this->storedEntityDeterminator->determine($className, $directories)) {
                $entities[$className] = $entity;
            }
        }

        return $entities;
    }

    public function gather(array $directories): ActionSet
    {
        $actions = new ActionSet();

        foreach ($this->scan($directories) as $className => $entity) {
            $this->processEntity($actions, $className, $entity);
        }

        return $actions;
    }

    private function processEntity(ActionSet $actions, string $className, Entity $entity): void
    {
        $storage = $this->storageManager->byName($entity->storage);
        $expectedStructure = $this->entityBlueprintBuilder->find($className);
        $newActions = $this->builderResolver->for($storage)
            ->buildActions($storage, $expectedStructure);

        foreach ($newActions as $action) {
            $actions[] = $action;
        }
    }
}
