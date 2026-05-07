<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure;

class ParentStores
{
    private array $classNames = [];
    private array $stores = [];
    private int $counter = -1;

    public function add(string $className, string|null $store): void
    {
        $this->stores[++$this->counter] = $store;
        $this->classNames[$className] = $this->counter;
    }

    public function getStore(string $className): string|null
    {
        $index = $this->classNames[$className];

        while ($index >= 0) {
            if ($this->stores[$index] !== null) {
                return $this->stores[$index];
            }

            --$index;
        }

        return null;
    }
}
