<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\{Attributes\ConfigValue, Attributes\Service, CachedImplementorList};
use Medas\MigrationBuilder\OriginalClassStorageStrategyActionBuilder;
use Medas\StorageManager\ConfigOptions\OriginalClassStorage\DefaultStrategy;
use Medas\StorageManager\Inheritance\OriginalClassStorageStrategy;

/**
 * Gathers every store the backend expects from all {@see ExpectedStructureProvider}s and
 * lets each storage's {@see StructureReader} turn them into the physical stores it keeps,
 * including stores that exist next to an entity's own, such as join tables and the stores
 * that keep the original class of an entity.
 */
#[Service]
readonly class ExpectedStructureCollector
{
    private CachedImplementorList $providers;

    public function __construct(
        #[ConfigValue(DefaultStrategy::class)]
        private OriginalClassStorageStrategy              $originalClassStorageStrategy,
        private ReaderResolver                            $readerResolver,
        private OriginalClassStorageStrategyActionBuilder $originalClassStorageBuilder,
    )
    {
        $this->providers = new CachedImplementorList(ExpectedStructureProvider::class);
    }

    public function collect(Settings $settings): ExpectedStructure
    {
        $structure = new ExpectedStructure();

        foreach ($this->providers->get() as $provider) {
            /** @var ExpectedStructureProvider $provider */
            foreach ($provider->provide($settings) as $expectedStore) {
                $this->add($structure, $expectedStore);
            }
        }

        return $structure;
    }

    private function add(ExpectedStructure $structure, ExpectedStore $expectedStore): void
    {
        $storage = $expectedStore->storage;
        $blueprint = $expectedStore->blueprint;

        if ($blueprint->name === null) {
            return;
        }

        $reader = $this->readerResolver->find($storage);

        if ($reader === null) {
            $structure->unreadableStorages[$storage->name()] = $storage;

            return;
        }

        foreach ($reader->expectedStores($storage, $blueprint) as $store) {
            $source = $store->description === null
                ? $expectedStore->source
                : $store->description . ' (' . $expectedStore->source . ')';

            $structure->add($storage, $store, $source);
        }

        // Only the store that asked for it keeps the original classes, not its children
        if ($blueprint->storeOriginalClass && $blueprint->storeRequestingOriginalClassStorage === $blueprint->name) {
            $blueprints = $this->originalClassStorageBuilder->blueprints(
                $this->originalClassStorageStrategy,
                $blueprint
            );

            foreach ($blueprints as $originalClassBlueprint) {
                foreach ($reader->expectedStores($storage, $originalClassBlueprint) as $store) {
                    $structure->add(
                        $storage,
                        $store,
                        'original classes (' . $expectedStore->source . ')'
                    );
                }
            }
        }
    }
}
