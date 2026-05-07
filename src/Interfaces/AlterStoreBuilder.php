<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Interfaces;

use Medas\MigrationBuilder\Structure\{Blueprint, Changes\Changes};
use Medas\StorageManager\{Interfaces\Storage, UnitOfWork\ActionSet};

interface AlterStoreBuilder
{
    public function build(Storage $storage, Blueprint $blueprint, Changes $changes): ActionSet;
}
