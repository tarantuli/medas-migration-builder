<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure;

class Blueprint
{
    public string|null $name = null;
    public string|null $parent = null;
    public bool $isMigrationStore = false;
    public bool $storeOriginalClass = false;
    public string $storeRequestingOriginalClassStorage;
    public string $originalEntityClassStorageStrategy;

    /** @var Blueprint\Field[] */
    public array $fields = [];

    /** @var Blueprint\Index[] */
    public array $indexes = [];

    /** @var Blueprint\ForeignKey[] */
    public array $foreignKeys = [];

    public function addField(Blueprint\Field $field): self
    {
        $field->store = $this->name;
        $this->fields[] = $field;

        return $this;
    }

    public function fieldByName(string $name): Blueprint\Field|null
    {
        return $this->fieldsByName([$name])[0] ?? null;
    }

    public function fieldsByName(array $names): array
    {
        return array_values(array_filter($this->fields, fn($field) => in_array($field->name, $names, true)));
    }

    public function addIndex(Blueprint\Index $index): self
    {
        if ($this->indexByHash($index->hash()) === null) {
            $this->indexes[] = $index;
        }

        return $this;
    }

    public function indexByHash(string $hash): Blueprint\Index|null
    {
        return $this->indexesByHash([$hash])[0] ?? null;
    }

    public function primaryIndex(): Blueprint\Index|null
    {
        return array_find($this->indexes, fn($index) => $index->isPrimary);
    }

    public function idField(): Blueprint\Field|null
    {
        return $this->primaryIndex()?->fields()[0];
    }

    public function indexesByHash(array $hashes): array
    {
        return array_values(array_filter($this->indexes, fn($index) => in_array($index->hash(), $hashes, true)));
    }

    public function addForeignKey(Blueprint\ForeignKey $foreignKey): self
    {
        if ($this->foreignKeyByHash($foreignKey->hash()) === null) {
            $this->foreignKeys[] = $foreignKey;
        }

        return $this;
    }

    public function foreignKeyByHash(string $hash): Blueprint\ForeignKey|null
    {
        return $this->foreignKeysByHash([$hash])[0] ?? null;
    }

    public function foreignKeysByHash(array $hashes): array
    {
        return array_values(array_filter(
            $this->foreignKeys,
            fn($foreignKey) => in_array($foreignKey->hash(), $hashes, true)
        ));
    }
}
