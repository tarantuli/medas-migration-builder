<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Providers;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\{
    MigrationFactory\ActionGatherer,
    Structure\Diff,
    Structure\EntityBlueprintBuilder
};
use Medas\StorageManager\StorageManager;

// The stores of all stored entities in the source directories
#[Service]
readonly class EntityStructureProvider implements Diff\ExpectedStructureProvider
{
    public function __construct(
        private ActionGatherer         $actionGatherer,
        private EntityBlueprintBuilder $entityBlueprintBuilder,
        private StorageManager         $storageManager,
    )
    {
    }

    public function provide(Diff\Settings $settings): iterable
    {
        foreach ($this->actionGatherer->scan($settings->sourceDirectories) as $className => $entity) {
            yield new Diff\ExpectedStore(
                $this->storageManager->byName($entity->storage),
                $this->entityBlueprintBuilder->find($className),
                'entity ' . $className,
            );
        }
    }
}
