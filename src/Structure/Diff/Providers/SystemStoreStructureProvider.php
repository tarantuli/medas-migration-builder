<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Providers;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\{
    MigrationFactory\MigrationStoreFileBuilder,
    MigrationFactory\SequenceStoreFileBuilder,
    Structure\Diff
};
use Medas\StorageManager\StorageManager;

// The stores the storage manager keeps for itself in the default storage
#[Service]
readonly class SystemStoreStructureProvider implements Diff\ExpectedStructureProvider
{
    public function __construct(
        private MigrationStoreFileBuilder $migrationStoreFileBuilder,
        private SequenceStoreFileBuilder  $sequenceStoreFileBuilder,
        private StorageManager            $storageManager,
    )
    {
    }

    public function provide(Diff\Settings $settings): iterable
    {
        $storage = $this->storageManager->byName();

        yield new Diff\ExpectedStore(
            $storage,
            $this->migrationStoreFileBuilder->blueprint(),
            'migrations'
        );

        if ($blueprint = $this->sequenceStoreFileBuilder->blueprint()) {
            yield new Diff\ExpectedStore($storage, $blueprint, 'sequences');
        }
    }
}
