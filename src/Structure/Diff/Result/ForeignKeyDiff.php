<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

use Medas\MigrationBuilder\Structure\Blueprint\ForeignKey;

readonly class ForeignKeyDiff
{
    /** @param string[] $differentProperties */
    public function __construct(
        public Status          $status,
        public ForeignKey|null $expected = null,
        public ForeignKey|null $existing = null,
        public array           $differentProperties = [],
    )
    {
    }

    // The expected foreign key, or the existing one for a superfluous foreign key
    public function foreignKey(): ForeignKey
    {
        return $this->expected ?? $this->existing;
    }

    public function isEqual(): bool
    {
        return $this->status === Status::Equal;
    }
}
