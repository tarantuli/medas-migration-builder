<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

use Medas\MigrationBuilder\Structure\Blueprint\{Field, Index};

readonly class IndexDiff
{
    public function __construct(
        public Status $status,

        // The expected index, or the existing one for a superfluous index
        public Index  $index,
    )
    {
    }

    /** @return string[] */
    public function fieldNames(): array
    {
        return array_map(fn(Field $field) => $field->name, $this->index->fields());
    }

    public function isEqual(): bool
    {
        return $this->status === Status::Equal;
    }
}
