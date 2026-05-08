<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{AsSingleton, BasePackage};
use Medas\FileBuilder\FileBuilderPackage;
use Medas\StorageManager\StorageManagerPackage;

class MigrationBuilderPackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return [
            FileBuilderPackage::instance(),
            StorageManagerPackage::instance(),
        ];
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
