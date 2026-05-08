<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\StorageManager\Inheritance\OriginalClassStorageStrategy;

class UnsupportedStrategy extends BaseException
{
    public function __construct(OriginalClassStorageStrategy $strategy)
    {
        parent::__construct($strategy::class);
    }

    public function pattern(): string
    {
        return 'Unsupported strategy class %s';
    }
}
