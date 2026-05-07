<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure;

use Medas\Core\Attributes\Service;
use Medas\EntityManager\{MetaData, MetaDataManager};

#[Service]
readonly class ParentStoreFinder
{
    public function __construct(
        private MetaDataManager $metaDataManager,
    )
    {
    }

    public function find(MetaData $metaData): ParentStores
    {
        $stores = new ParentStores();

        $stores->add($metaData->className, $metaData->entity->store);

        while ($parent = $metaData->inheritance->parent) {
            $metaData = $this->metaDataManager->get($parent);

            $stores->add($metaData->className, $metaData->entity->store);
        }

        return $stores;
    }
}
