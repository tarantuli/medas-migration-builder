<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

/**
 * The complete comparison between everything the backend expects to find in its storages
 * and what those storages actually contain.
 */
class StructureDiff
{
    /** @var array<string, StorageDiff> Keyed by storage name */
    public array $storages = [];

    /**
     * @var string[] Names of storages the backend uses, but that have no structure reader,
     *               so they could not be compared
     */
    public array $unreadableStorages = [];

    public function storage(string $name): StorageDiff
    {
        return $this->storages[$name] ??= new StorageDiff($name);
    }

    public function store(string $storageName, string $storeName): StoreDiff|null
    {
        return ($this->storages[$storageName] ?? null)?->store($storeName);
    }

    /** @return StoreDiff[] */
    public function stores(): array
    {
        return array_merge(...array_values(array_map(
            fn(StorageDiff $storage) => array_values($storage->stores),
            $this->storages
        )));
    }

    /** @return StoreDiff[] */
    public function storesWithStatus(Status ...$statuses): array
    {
        return array_values(array_filter(
            $this->stores(),
            fn(StoreDiff $store) => in_array($store->status, $statuses, true)
        ));
    }

    public function severity(): Severity
    {
        return Severity::worst(...array_map(fn(StorageDiff $storage) => $storage->severity(), $this->storages));
    }

    public function hasDifferences(bool $includeCompatible = false): bool
    {
        $severity = $this->severity();

        return $includeCompatible
            ? $severity !== Severity::Equal
            : $severity === Severity::Incompatible;
    }
}
