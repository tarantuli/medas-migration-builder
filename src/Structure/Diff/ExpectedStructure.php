<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\StorageManager\Interfaces\Storage;

/**
 * All physical stores the backend expects, per storage, as gathered by the
 * {@see ExpectedStructureCollector}
 */
class ExpectedStructure
{
    /** @var array<string, Storage> Keyed by storage name */
    public array $storages = [];

    /** @var array<string, array<string, NarrowedStore>> Keyed by storage name, then store name */
    public array $stores = [];

    /** @var array<string, array<string, string[]>> What each store is expected for, keyed like $stores */
    public array $sources = [];

    /** @var array<string, Storage> Storages without a structure reader, keyed by name */
    public array $unreadableStorages = [];

    public function addStorage(Storage $storage): void
    {
        $this->storages[$storage->name()] ??= $storage;
        $this->stores[$storage->name()] ??= [];
    }

    // Adds a store; a store that is expected more than once gets the parts of both
    public function add(Storage $storage, NarrowedStore $store, string $source): void
    {
        $this->addStorage($storage);

        $storageName = $storage->name();
        $storeName = $store->name();

        if (isset($this->stores[$storageName][$storeName])) {
            $this->merge($this->stores[$storageName][$storeName], $store);
        }
        else {
            $this->stores[$storageName][$storeName] = $store;
        }

        if (!in_array($source, $this->sources[$storageName][$storeName] ?? [], true)) {
            $this->sources[$storageName][$storeName][] = $source;
        }
    }

    private function merge(NarrowedStore $target, NarrowedStore $other): void
    {
        foreach ($other->blueprint->fields as $field) {
            if ($target->blueprint->fieldByName($field->name) === null) {
                $target->blueprint->addField($field);
            }
        }

        // Index and foreign key hashes keep these unique already
        foreach ($other->blueprint->indexes as $index) {
            $target->blueprint->addIndex($index);
        }

        foreach ($other->blueprint->foreignKeys as $foreignKey) {
            $target->blueprint->addForeignKey($foreignKey);
        }

        $collectionFieldNames = array_map(fn($field) => $field->name, $target->collectionFields);

        foreach ($other->collectionFields as $field) {
            if (!in_array($field->name, $collectionFieldNames, true)) {
                $target->collectionFields[] = $field;
            }
        }

        $target->addedForeignKeyHashes = array_values(array_unique([
            ...$target->addedForeignKeyHashes,
            ...$other->addedForeignKeyHashes,
        ]));
    }

    public function store(string $storageName, string $storeName): NarrowedStore|null
    {
        return $this->stores[$storageName][$storeName] ?? null;
    }

    /** @return string[] */
    public function sourcesOf(string $storageName, string $storeName): array
    {
        return $this->sources[$storageName][$storeName] ?? [];
    }
}
