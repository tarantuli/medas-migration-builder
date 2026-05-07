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
        private FileLoader                      $fileLoader,
        private MigrationBuilderManager         $migrationBuilderManager,
        private StorageManager                  $storageManager,
        private StoredEntityDeterminator        $storedEntityDeterminator,
        private Structure\EntityStructureFinder $entityStructureFinder,
    )
    {
    }

    public function gather(array $directories): ActionSet
    {
        $actions = new ActionSet();

        foreach ($directories as &$directory) {
            $directory = realpath($directory);

            $this->fileLoader->load($directory);
        }

        foreach (get_declared_classes() as $className) {
            if (null === $entity = $this->storedEntityDeterminator->determine($className, $directories)) {
                continue;
            }

            $this->processEntity($actions, $className, $entity);
        }

        return $actions;
    }

    private function processEntity(ActionSet $actions, string $className, Entity $entity): void
    {
        $storage = $this->storageManager->byName($entity->storage);
        $expectedStructure = $this->entityStructureFinder->find($className);
        $newActions = $this->migrationBuilderManager->for($storage)
            ->buildActions($storage, $expectedStructure);

        foreach ($newActions as $action) {
            $actions[] = $action;
        }
    }
}
