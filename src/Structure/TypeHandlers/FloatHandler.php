<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\TypeHandlers;

use Medas\Core\Attributes\Service;
use Medas\EntityManager\MetaData\Property;
use Medas\StorageManager\Type;

#[Service]
readonly class FloatHandler extends BaseHandler
{
    public function fieldType(Property|null $property): Type
    {
        return Type::Float;
    }
}
