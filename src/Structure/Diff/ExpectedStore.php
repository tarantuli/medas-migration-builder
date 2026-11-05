<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\Interfaces\Storage;

/**
 * A store the backend wants to use, as a provider describes it: the logical blueprint,
 * e.g. an entity's, before the storage's driver narrows it to physical stores.
 */
readonly class ExpectedStore
{
    public function __construct(
        public Storage   $storage,
        public Blueprint $blueprint,

        // What the backend wants the store for, e.g. "entity App\Entities\Order"
        public string    $source,
    )
    {
    }
}
