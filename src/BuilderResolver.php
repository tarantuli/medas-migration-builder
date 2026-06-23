<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{Attributes\Service, CachedImplementorList};
use Medas\StorageManager\Interfaces\Storage;

#[Service]
readonly class BuilderResolver
{
    private CachedImplementorList $migrationBuilders;

    public function __construct()
    {
        $this->migrationBuilders = new CachedImplementorList(MigrationBuilder::class);
    }

    public function find(Storage $storage): MigrationBuilder
    {
        foreach ($this->migrationBuilders->get() as $builder) {
            /** @var MigrationBuilder $builder */
            if ($builder->handles($storage)) {
                return $builder;
            }
        }

        throw new Exceptions\NoMigrationBuilderFoundForStorage($storage);
    }
}
