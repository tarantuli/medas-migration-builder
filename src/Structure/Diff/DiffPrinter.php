<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Console\{Formats\SafeColor, Printer, Text};
use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\Type;

/**
 * Prints a structure diff to the console, one block per storage:
 *
 *   + store or field the backend expects, but the storage doesn't have
 *   - store or field the storage has, but the backend doesn't expect
 *   ~ store or field that differs (gray when the backend can live with the difference)
 *   * fields that are in a different order
 */
#[Service]
readonly class DiffPrinter
{
    private const string INDENT = '  ';

    public function __construct(
        private Printer $printer,
    )
    {
    }

    /** @param bool $onlyProblems Leave out differences the backend can live with */
    public function print(Result\StructureDiff $diff, bool $onlyProblems = false): void
    {
        $this->printer->printEol();

        foreach ($diff->unreadableStorages as $storageName) {
            $this->printer->printLine(
                Text::create('storage ' . $storageName, SafeColor::White),
                Text::create(
                    ' could not be compared: no structure reader handles it',
                    SafeColor::Orange
                ),
            );
        }

        if ($diff->unreadableStorages) {
            $this->printer->printEol();
        }

        foreach ($diff->storages as $storage) {
            $this->printStorage($storage, $onlyProblems);
        }

        $this->printSummary($diff);
    }

    private function printStorage(Result\StorageDiff $storage, bool $onlyProblems): void
    {
        $this->printer
            ->printLine(Text::create('storage ' . $storage->name, SafeColor::White))
            ->printEol();

        $stores = array_filter(
            $storage->stores,
            fn(Result\StoreDiff $store)
                => $onlyProblems ? $store->hasIncompatibleDifferences() : !$store->isEqual(
            )
        );

        $width = max([0, ...array_map(fn(Result\StoreDiff $store) => mb_strlen($store->name), $stores)]);

        foreach ($stores as $store) {
            $this->printStore($store, $width, $onlyProblems);
        }

        $equalCount = count($storage->storesWithStatus(Result\Status::Equal));
        $hiddenCount = count($storage->stores) - count($stores) - $equalCount;

        if ($equalCount > 0) {
            $this->printer->printLine(Text::create(
                self::INDENT
                    . sprintf('= %d %s equal', $equalCount, $equalCount === 1 ? 'store is' : 'stores are'),
                SafeColor::Gray
            ));
        }

        if ($hiddenCount > 0) {
            $this->printer->printLine(Text::create(
                self::INDENT
                    . sprintf(
                        '~ %d %s only compatible differences',
                        $hiddenCount,
                        $hiddenCount === 1 ? 'store has' : 'stores have'
                    ),
                SafeColor::Gray
            ));
        }

        $this->printer->printEol();
    }

    private function printStore(Result\StoreDiff $store, int $width, bool $onlyProblems): void
    {
        [$symbol, $color] = match ($store->status) {
            Result\Status::Missing => ['+',
            SafeColor::LightGreen], Result\Status::Superfluous => ['-',
            SafeColor::LightRed], default => $store->hasIncompatibleDifferences() ? ['~',

            SafeColor::Yellow] : ['~', SafeColor::Gray],
        };

        $this->printer->printLine(
            Text::create(self::INDENT . $symbol . ' ' . $this->pad($store->name, $width), $color),
            Text::create('  ' . $this->pad($store->status->value, 11), $color),
            Text::create($store->sources ? '  ' . implode(', ', $store->sources) : '', SafeColor::Gray),
        );

        if ($store->status === Result\Status::Missing || $store->status === Result\Status::Superfluous) {
            $blueprint = $store->expected ?? $store->existing;
            $names = array_map(fn(Blueprint\Field $field) => $field->name, $blueprint->fields);

            $this->printer->printLine(Text::create(
                str_repeat(self::INDENT, 3) . 'fields: ' . ($names ? implode(', ', $names) : '(none)'),
                SafeColor::Gray
            ));

            return;
        }

        $this->printParts($store, $onlyProblems);
    }

    private function printParts(Result\StoreDiff $store, bool $onlyProblems): void
    {
        // Each row: [symbol, label, status, details, color]
        $rows = [];

        foreach ($store->fields as $field) {
            if ($field->isEqual() || ($onlyProblems && $field->severity !== Result\Severity::Incompatible)) {
                continue;
            }

            $rows[] = match ($field->status) {
                Result\Status::Missing => ['+',

                $field->name, 'missing', $this->describeField($field->expected), SafeColor::LightGreen], Result\Status::Superfluous
                    => ['-',

                $field->name, 'superfluous', $this->describeField($field->existing), SafeColor::LightRed], default
                    => ['~',

                $field->name, $field->severity === Result\Severity::Incompatible
                    ? 'changed' : 'compatible',
                        $this->describeFieldChanges($field), $field->severity === Result\Severity::Incompatible
                            ? SafeColor::Yellow : SafeColor::Gray,],
            };
        }

        foreach ($store->indexes as $index) {
            if ($index->isEqual()) {
                continue;
            }

            $rows[] = $index->status === Result\Status::Missing
                ? ['+', $this->describeIndex($index->index), 'missing', '', SafeColor::LightGreen]
                : ['-', $this->describeIndex($index->index), 'superfluous', '', SafeColor::LightRed];
        }

        foreach ($store->foreignKeys as $foreignKey) {
            if ($foreignKey->isEqual()) {
                continue;
            }

            $label = $this->describeForeignKey($foreignKey->foreignKey());
            $rows[] = match ($foreignKey->status) {
                Result\Status::Missing => ['+',
                $label, 'missing', '', SafeColor::LightGreen], Result\Status::Superfluous => ['-',
                $label, 'superfluous', '', SafeColor::LightRed], default => ['~',

                $label, 'changed', $this->describeChanges(
                    $foreignKey->differentProperties,
                    $foreignKey->expected,
                    $foreignKey->existing
                ), SafeColor::Yellow,],
            };
        }

        if (!$onlyProblems && $store->order && !$store->order->isEqual()) {
            $rows[] = [
                '*',
                'field order',
                'different',
                sprintf(
                    'expected %s; found %s; move %s',
                    implode(', ', $store->order->expectedOrder),
                    implode(', ', $store->order->existingOrder),
                    implode(', ', $store->order->movedFields),
                ),
                SafeColor::Cyan,
            ];
        }

        $labelWidth = max([0, ...array_map(fn(array $row) => mb_strlen($row[1]), $rows)]);

        foreach ($rows as [$symbol, $label, $status, $details, $color]) {
            $this->printer->printLine(
                Text::create(
                    str_repeat(self::INDENT, 3) . $symbol . ' ' . $this->pad($label, $labelWidth),
                    $color
                ),
                Text::create('  ' . $this->pad($status, 11), $color),
                Text::create($details === '' ? '' : '  ' . $details, SafeColor::LightGray),
            );
        }
    }

    private function describeField(Blueprint\Field $field): string
    {
        $parts = [$field->type->value];

        if (in_array($field->type, [Type::Text, Type::Binary], true)) {
            $parts[] = 'length ' . $field->minLength . '..' . $field->maxLength;
        }

        if (in_array($field->type, [Type::Integer, Type::Float], true)) {
            $parts[] = 'range '
                . $this->formatValue($field->minValue)
                . '..'
                . $this->formatValue($field->maxValue);
        }

        foreach ([
            'isNullable' => 'nullable',
            'isUnique' => 'unique',
            'isGenerated' => 'generated'
        ] as $property => $label) {
            if ($field->$property) {
                $parts[] = $label;
            }
        }

        if ($field->hasDefault) {
            $parts[] = 'default ' . $this->formatValue($field->default);
        }

        return implode(', ', $parts);
    }

    private function describeFieldChanges(Result\FieldDiff $field): string
    {
        return $this->describeChanges(
            [...$field->incompatibleProperties, ...$field->compatibleProperties],
            $field->expected,
            $field->existing
        );
    }

    private function describeIndex(Blueprint\Index $index): string
    {
        $kind = match (true) {
            $index->isPrimary => 'primary key',
            $index->isUnique => 'unique index',
            default => 'index',
        };

        $names = array_map(fn(Blueprint\Field $field) => $field->name, $index->fields());

        return $kind . ' (' . implode(', ', $names) . ')';
    }

    private function describeForeignKey(Blueprint\ForeignKey $foreignKey): string
    {
        return sprintf(
            'foreign key %s -> %s.%s',
            $foreignKey->field,
            $foreignKey->foreignEntity,
            $foreignKey->foreignField
        );
    }

    /** @param string[] $properties */
    private function describeChanges(array $properties, object $expected, object $existing): string
    {
        return implode(
            '; ',
            array_map(
                fn(string $property)
                    => sprintf(
                        '%s: expected %s, found %s',
                        $property,
                        $this->formatValue($expected->$property),
                        $this->formatValue($existing->$property)
                    ),
                $properties
            )
        );
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'yes' : 'no',
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof Blueprint\Field => $value->name,
            is_float($value) && floor($value) === $value && abs($value) < 1e21 => number_format($value, 0, '.', ''),
            is_scalar($value) => is_string($value) ? '"' . $value . '"' : (string) $value,
            default => (string) json_encode($value),
        };
    }

    private function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    private function printSummary(Result\StructureDiff $diff): void
    {
        $counts = [];

        foreach ([
            Result\Status::Missing,
            Result\Status::Superfluous,
            Result\Status::Changed,
            Result\Status::Equal
        ] as $status) {
            $count = count($diff->storesWithStatus($status));

            if ($count > 0) {
                $counts[] = $count . ' ' . $status->value;
            }
        }

        $severity = $diff->severity();
        $verdict = match ($severity) {
            Result\Severity::Equal
                => Text::create('the storages match the backend', SafeColor::LightGreen),

            Result\Severity::Compatible
                => Text::create('the storages are compatible with the backend', SafeColor::LightGreen),

            Result\Severity::Incompatible
                => Text::create('the storages differ from the backend', SafeColor::Yellow),
        };

        $this->printer->printLine(
            $verdict,
            Text::create(
                $counts ? ' (stores: ' . implode(', ', $counts) . ')' : ' (no stores)',
                SafeColor::Gray
            ),
        );
    }
}
