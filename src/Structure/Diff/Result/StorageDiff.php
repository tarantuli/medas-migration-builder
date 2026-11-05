<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

class StorageDiff
{
    /** @var array<string, StoreDiff> Keyed by store name */
    public array $stores = [];

    public function __construct(
        public readonly string $name,
    )
    {
    }

    public function add(StoreDiff $store): void
    {
        $this->stores[$store->name] = $store;
    }

    public function store(string $name): StoreDiff|null
    {
        return $this->stores[$name] ?? null;
    }

    /** @return StoreDiff[] */
    public function storesWithStatus(Status ...$statuses): array
    {
        return array_values(array_filter(
            $this->stores,
            fn(StoreDiff $store) => in_array($store->status, $statuses, true)
        ));
    }

    public function severity(): Severity
    {
        return Severity::worst(...array_map(fn(StoreDiff $store) => $store->severity(), $this->stores));
    }
}
