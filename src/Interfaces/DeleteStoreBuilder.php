<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Interfaces;

use Medas\StorageManager\{Interfaces\Store, UnitOfWork\ActionSet};

interface DeleteStoreBuilder
{
    public function build(Store $store): ActionSet;
}
