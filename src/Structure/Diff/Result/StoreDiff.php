<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

use Medas\MigrationBuilder\Structure\Blueprint;

class StoreDiff
{
    /** @var FieldDiff[] Expected fields in expected order, followed by the superfluous ones */
    public array $fields = [];

    /** @var IndexDiff[] */
    public array $indexes = [];

    /** @var ForeignKeyDiff[] */
    public array $foreignKeys = [];

    // Only filled when the field order was checked and the store exists on both sides
    public FieldOrderDiff|null $order = null;

    /**
     * @param string[] $sources What the backend expects this store for, e.g. an entity class
     */
    public function __construct(
        public readonly string         $storageName,
        public readonly string         $name,
        public Status                  $status = Status::Equal,
        public readonly Blueprint|null $expected = null,
        public readonly Blueprint|null $existing = null,
        public array                   $sources = [],
    )
    {
    }

    public function isEqual(): bool
    {
        return $this->status === Status::Equal;
    }

    // The worst severity found in this store; a missing or superfluous store is incompatible
    public function severity(): Severity
    {
        if ($this->status === Status::Missing || $this->status === Status::Superfluous) {
            return Severity::Incompatible;
        }

        $severities = array_map(fn(FieldDiff $field) => $field->severity, $this->fields);

        foreach ([...$this->indexes, ...$this->foreignKeys] as $diff) {
            if (!$diff->isEqual()) {
                $severities[] = Severity::Incompatible;
            }
        }

        if ($this->order && !$this->order->isEqual()) {
            $severities[] = Severity::Compatible;
        }

        return Severity::worst(...$severities);
    }

    public function hasIncompatibleDifferences(): bool
    {
        return $this->severity() === Severity::Incompatible;
    }

    /** @return FieldDiff[] */
    public function fieldsWithStatus(Status ...$statuses): array
    {
        return array_values(array_filter(
            $this->fields,
            fn(FieldDiff $field) => in_array($field->status, $statuses, true)
        ));
    }

    /** @return IndexDiff[] */
    public function indexesWithStatus(Status ...$statuses): array
    {
        return array_values(array_filter(
            $this->indexes,
            fn(IndexDiff $index) => in_array($index->status, $statuses, true)
        ));
    }

    /** @return ForeignKeyDiff[] */
    public function foreignKeysWithStatus(Status ...$statuses): array
    {
        return array_values(array_filter(
            $this->foreignKeys,
            fn(ForeignKeyDiff $foreignKey) => in_array($foreignKey->status, $statuses, true)
        ));
    }

    // Derives the store's status from its parts; called once all parts have been added
    public function determineStatus(): void
    {
        if ($this->status === Status::Missing || $this->status === Status::Superfluous) {
            return;
        }

        $hasDifferences = array_any(
            [...$this->fields, ...$this->indexes, ...$this->foreignKeys],
            fn(FieldDiff|IndexDiff|ForeignKeyDiff $diff) => !$diff->isEqual()
        );

        if ($this->order && !$this->order->isEqual()) {
            $hasDifferences = true;
        }

        $this->status = $hasDifferences ? Status::Changed : Status::Equal;
    }
}
