<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{Attributes\Service, Interfaces\ImplementorFinder, Interfaces\ServiceManager};
use Medas\StorageManager\Interfaces\Storage;

#[Service]
readonly class MigrationBuilderManager
{
    public function __construct(
        private ServiceManager $serviceManager,
    )
    {
    }

    public function for(Storage $storage): MigrationBuilder
    {
        foreach ($this->serviceManager->resolve(ImplementorFinder::class)->find(MigrationBuilder::class) as $builder) {
            if ($builder->handles($storage)) {
                return $builder;
            }
        }

        throw new Exceptions\NoMigrationBuilderFoundForStorage($storage);
    }
}
