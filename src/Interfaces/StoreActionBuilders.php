<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Interfaces;

interface StoreActionBuilders
{
    public function createStore(): CreateStoreBuilder;

    public function deleteStore(): DeleteStoreBuilder;
}
