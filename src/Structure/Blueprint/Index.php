<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Blueprint;

class Index
{
    /** @param Field[] $fields */
    public function __construct(
        private array $fields = [],
        public bool   $isPrimary = false,
        public bool   $isUnique = false,
    )
    {
    }

    public function addField(Field $field): void
    {
        $this->fields[] = $field;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function hash(): string
    {
        return array_reduce($this->fields, fn(string $carry, Field $field) => $carry . $field->name . "\0", '')
            . (int) $this->isPrimary
            . (int) $this->isUnique;
    }
}
