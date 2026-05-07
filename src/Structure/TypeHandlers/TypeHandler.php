<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\TypeHandlers;

use Medas\EntityManager\MetaData\Property;
use Medas\MigrationBuilder\Structure\Blueprint\ForeignKey;
use Medas\StorageManager\Type;

interface TypeHandler
{
    public function foreignKey(Property $property): ForeignKey|null;

    public function fieldType(Property|null $property): Type;
}
