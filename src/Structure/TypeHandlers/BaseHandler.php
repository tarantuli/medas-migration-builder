<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\TypeHandlers;

use Medas\EntityManager\MetaData\Property;
use Medas\MigrationBuilder\Structure\Blueprint\ForeignKey;

readonly abstract class BaseHandler implements TypeHandler
{
    public function foreignKey(Property $property): ForeignKey|null
    {
        return null;
    }
}
