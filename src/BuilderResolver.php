<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{
    Attributes\Service,
    CachedImplementorList,
    Interfaces\Cache,
    Interfaces\CacheManager
};
use Medas\StorageManager\Interfaces\Storage;

#[Service]
readonly class BuilderResolver
{
    private Cache $cache;
    private CachedImplementorList $cachedImplementorList;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cache = $cacheManager->get('memory');
        $this->cachedImplementorList = new CachedImplementorList(MigrationBuilder::class);
    }

    public function for(Storage $storage): MigrationBuilder
    {
        return $this->cache->get(__CLASS__ . $storage->name(), fn() => $this->find($storage));
    }

    private function find(Storage $storage): MigrationBuilder
    {
        foreach ($this->cachedImplementorList->get() as $builder) {
            /** @var MigrationBuilder $builder */
            if ($builder->handles($storage)) {
                return $builder;
            }
        }

        throw new Exceptions\NoMigrationBuilderFoundForStorage($storage);
    }
}
