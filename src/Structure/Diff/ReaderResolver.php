<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\{Attributes\Service, CachedImplementorList};
use Medas\StorageManager\Interfaces\Storage;

#[Service]
readonly class ReaderResolver
{
    private CachedImplementorList $readers;

    public function __construct()
    {
        $this->readers = new CachedImplementorList(StructureReader::class);
    }

    public function find(Storage $storage): StructureReader|null
    {
        /** @var StructureReader $reader */
        return array_find($this->readers->get(), fn($reader) => $reader->handles($storage));
    }
}
