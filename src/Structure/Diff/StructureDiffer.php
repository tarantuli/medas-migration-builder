<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\Attributes\{ConfigValue, Service};
use Medas\MigrationBuilder\ConfigOptions\IgnoredStores;
use Medas\StorageManager\Interfaces\Storage;

/**
 * Compares everything the backend expects to find in its storages with what those
 * storages actually contain: missing stores and fields, but also superfluous ones,
 * and optionally fields in the wrong order.
 *
 * Only storages the backend uses are compared, as only those are known.
 */
#[Service]
readonly class StructureDiffer
{
    public function __construct(
        private ExpectedStructureCollector $expectedStructureCollector,

        #[ConfigValue(IgnoredStores::class)]
        private array                      $ignoredStores,
        private ReaderResolver             $readerResolver,
        private StoreDiffer                $storeDiffer,
    )
    {
    }

    public function diff(Settings $settings): Result\StructureDiff
    {
        $expected = $this->expectedStructureCollector->collect($settings);
        $diff = new Result\StructureDiff();

        $diff->unreadableStorages = array_keys($expected->unreadableStorages);

        foreach ($expected->storages as $storageName => $storage) {
            if ($settings->storage !== null && $settings->storage !== $storageName) {
                continue;
            }

            $this->diffStorage($diff, $expected, $storage, $settings);
        }

        return $diff;
    }

    private function diffStorage(
        Result\StructureDiff $diff,
        ExpectedStructure    $expected,
        Storage              $storage,
        Settings             $settings
    ): void
    {
        $reader = $this->readerResolver->find($storage);
        $storageDiff = $diff->storage($storage->name());

        // Existing store names keyed by their lowercase form: some databases (MySQL on
        // Windows, for one) change the case of the names they're given
        $existingNames = [];

        foreach ($reader->storeNames($storage) as $storeName) {
            $existingNames[strtolower($storeName)] = $storeName;
        }

        $matchedNames = [];

        foreach ($expected->stores[$storage->name()] as $storeName => $store) {
            if (!$this->isSelected($storeName, $settings)) {
                continue;
            }

            $existingName = $existingNames[strtolower($storeName)] ?? null;
            $matchedNames[] = $existingName;

            $storageDiff->add($this->storeDiffer->diff(
                $storage->name(),
                $store->blueprint,
                $existingName === null ? null : $reader->read($storage, $existingName),
                $settings->checkOrder,
                $expected->sourcesOf($storage->name(), $storeName),
            ));
        }

        $ignoredPatterns = [...$this->ignoredStores, ...$settings->ignoredStores];

        foreach ($existingNames as $storeName) {
            if (in_array($storeName, $matchedNames, true)
                    || !$this->isSelected($storeName, $settings)
                    || $this->isIgnored($storeName, $ignoredPatterns)) {
                continue;
            }

            $existing = $reader->read($storage, $storeName);

            if ($existing === null) {
                continue;
            }

            $storageDiff->add($this->storeDiffer->diff($storage->name(), null, $existing));
        }
    }

    private function isSelected(string $storeName, Settings $settings): bool
    {
        return $settings->store === null || fnmatch($settings->store, $storeName);
    }

    /** @param string[] $patterns */
    private function isIgnored(string $storeName, array $patterns): bool
    {
        return array_any($patterns, fn(string $pattern) => fnmatch($pattern, $storeName));
    }
}
