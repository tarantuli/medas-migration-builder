<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

use Medas\MigrationBuilder\Structure\Blueprint\Field;

readonly class FieldDiff
{
    public static function missing(Field $expected): self
    {
        return new self(
            $expected->name,
            Status::Missing,
            $expected,
            severity: Severity::Incompatible
        );
    }

    public static function superfluous(Field $existing): self
    {
        return new self(
            $existing->name,
            Status::Superfluous,
            existing: $existing,
            severity: Severity::Incompatible
        );
    }

    /**
     * @param string[] $incompatibleProperties The Field properties that differ in a way a migration should resolve
     * @param string[] $compatibleProperties   The Field properties that differ in a way the backend can live with
     */
    public function __construct(
        public string     $name,
        public Status     $status,
        public Field|null $expected = null,
        public Field|null $existing = null,
        public Severity   $severity = Severity::Equal,
        public array      $incompatibleProperties = [],
        public array      $compatibleProperties = [],
    )
    {
    }

    public function isEqual(): bool
    {
        return $this->status === Status::Equal;
    }
}
