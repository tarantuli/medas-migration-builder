<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\MigrationBuilder\Structure\Blueprint;

/**
 * A blueprint narrowed down to what physically lives in its own store: no fields,
 * indexes or foreign keys that belong to a parent's store, and no collection fields,
 * which live in stores of their own.
 */
class NarrowedStore
{
    /**
     * @param Blueprint\Field[] $collectionFields       The collection fields of the store's own entity
     * @param string[]          $addedForeignKeyHashes  Foreign keys that the narrowing added, i.e. the one
     *                                                  linking an inherited primary key to the parent's store
     * @param string|null       $description            What this store is for, when it isn't an entity's
     *                                                  own store, e.g. "join table for orders.lines"
     */
    public function __construct(
        public Blueprint   $blueprint,
        public array       $collectionFields = [],
        public array       $addedForeignKeyHashes = [],
        public string|null $description = null,
    )
    {
    }

    public function name(): string
    {
        return (string) $this->blueprint->name;
    }

    // Whether something assigned to $store lives outside this store
    public function isStoredElsewhere(string|null $store): bool
    {
        return $store !== null && $store !== $this->blueprint->name;
    }
}
