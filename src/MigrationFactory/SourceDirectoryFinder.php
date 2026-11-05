<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

use Medas\Core\{
    Attributes\ConfigValue,
    Attributes\Service,
    CachedImplementorList,
    Interfaces\PackageEntities
};
use Medas\EntityManager\ConfigOptions\EntityDirectories;

/**
 * The directories that hold the entity classes: the configured entity directories plus
 * those of every package with entities of its own.
 */
#[Service]
readonly class SourceDirectoryFinder
{
    private CachedImplementorList $packageEntities;

    public function __construct(
        #[ConfigValue(EntityDirectories::class)]
        private array $entityDirectories,
    )
    {
        $this->packageEntities = new CachedImplementorList(PackageEntities::class);
    }

    /** @return string[] */
    public function find(): array
    {
        $directories = $this->entityDirectories;

        foreach ($this->packageEntities->get() as $packageEntities) {
            /** @var PackageEntities $packageEntities */
            $directories = array_merge($directories, $packageEntities->directories());
        }

        return $directories;
    }
}
