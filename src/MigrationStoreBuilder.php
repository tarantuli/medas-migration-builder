<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\StorageManager\Interfaces\{
    Builders\MigrationStoreBuilder as MigrationStoreBuilderInterface,
    Store
};
use Medas\StorageManager\StorageManager;
use Medas\StorageManager\Type;

#[Service]
readonly class MigrationStoreBuilder implements MigrationStoreBuilderInterface
{
    public function __construct(
        private BuilderResolver $migrationBuilderManager,
        private StorageManager  $storageManager,
    )
    {
    }

    public function build(Store $store): void
    {
        $blueprint = new Structure\Blueprint();

        $blueprint->name = $store->name();
        $migrationField = new Structure\Blueprint\Field('migration', Type::Text);
        $datetimeField = new Structure\Blueprint\Field('migratedAt', Type::DateTime);

        $blueprint->addField($migrationField);
        $blueprint->addField($datetimeField);
        $blueprint->addIndex(new Structure\Blueprint\Index([$migrationField]));

        $storage = $store->storage();
        $actions = $this->migrationBuilderManager->for($storage)
            ->buildActions($storage, $blueprint);

        $this->storageManager->controller($storage)->actionExecutor()->executeSet($actions);
    }
}
