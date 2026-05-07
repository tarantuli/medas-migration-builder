<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Interfaces;

use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\{Interfaces\Storage, UnitOfWork\ActionSet};

interface CreateStoreBuilder
{
    public function build(Storage $storage, Blueprint $blueprint): ActionSet;
}
