<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\StorageManager\Interfaces\Storage;

class NoMigrationBuilderFoundForStorage extends BaseException
{
    public function __construct(Storage $storage)
    {
        parent::__construct($storage->name());
    }

    public function pattern(): string
    {
        return 'no migration builder found for storage "%s"';
    }
}
