<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\OriginalClassStorageActionBuilders;

use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\{Interfaces\Storage, UnitOfWork\ActionSet};

interface ActionBuilderInterface
{
    public function buildStoreActions(Blueprint $blueprint, Storage $storage): ActionSet;
}
