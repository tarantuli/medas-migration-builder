<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\Interfaces\Storage;

/**
 * The driver-specific side of the structure diff: reads what a storage contains, and
 * knows which physical stores the driver creates for a blueprint. Implementations are
 * found automatically, like migration builders.
 */
interface StructureReader
{
    public function handles(Storage $storage): bool;

    /** @return string[] The names of all stores the storage contains */
    public function storeNames(Storage $storage): array;

    // The structure of the store with this name, null if the storage doesn't have it
    public function read(Storage $storage, string $storeName): Blueprint|null;

    /**
     * The physical stores this driver creates for the given blueprint: the blueprint
     * narrowed to what its own store holds, plus any stores of its own, such as join
     * tables for collections. These must match what {@see read()} returns once created.
     *
     * @return NarrowedStore[]
     */
    public function expectedStores(Storage $storage, Blueprint $blueprint): array;
}
