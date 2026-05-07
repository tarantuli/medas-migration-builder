<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\FileBuilder\PhpClass\MethodDefinition;
use Medas\StorageManager\{Interfaces\Storage, UnitOfWork\ActionSet};

interface MigrationBuilder
{
    public function build(
        Storage             $storage,
        Structure\Blueprint $expectedStructure,
        MethodDefinition    $migrateMethod,
        MethodDefinition    $undoMethod,
        bool                $ignoreExistingStructure = false,
    ): bool;

    public function buildActions(
        Storage             $storage,
        Structure\Blueprint $blueprint,
        bool                $ignoreExistingStructure = false,
    ): ActionSet;
}
