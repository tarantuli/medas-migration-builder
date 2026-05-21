<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{Attributes\Service,
    Interfaces\Cache,
    Interfaces\CacheManager,
    Interfaces\ImplementorFinder,
    Interfaces\ServiceManager};
use Medas\StorageManager\Interfaces\Storage;

#[Service]
readonly class BuilderResolver
{
    private Cache $cache;

    public function __construct(
        private ServiceManager $serviceManager,
        CacheManager $cacheManager,
    )
    {
        $this->cache = $cacheManager->get('memory');
    }

    public function for(Storage $storage): MigrationBuilder
    {
        return $this->cache->get(__CLASS__ . $storage->name(), fn() => $this->find($storage));
    }

    private function find(Storage $storage): MigrationBuilder
    {
        foreach ($this->serviceManager->resolve(ImplementorFinder::class)->find(MigrationBuilder::class) as $builder) {
            if ($builder->handles($storage)) {
                return $builder;
            }
        }

        throw new Exceptions\NoMigrationBuilderFoundForStorage($storage);
    }
}
