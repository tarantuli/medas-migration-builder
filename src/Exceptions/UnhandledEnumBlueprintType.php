<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\StorageManager\Type;

class UnhandledEnumBlueprintType extends BaseException
{
    public function __construct(string $enumClassName, Type $blueprintType)
    {
        parent::__construct($blueprintType, $enumClassName);
    }

    public function pattern(): string
    {
        return 'unhandled blueprint type %s for enum %s';
    }
}
