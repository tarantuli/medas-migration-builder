<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\Blueprint;

/**
 * Compares one expected store with what a storage has under that name. The expected
 * blueprint should already be narrowed to what physically lives in the store (see
 * {@see StoreNarrower}), otherwise everything a parent's store owns shows up as missing.
 */
#[Service]
readonly class StoreDiffer
{
    public function __construct(
        private FieldComparer $fieldComparer = new FieldComparer(),
    )
    {
    }

    /** @param string[] $sources */
    public function diff(
        string         $storageName,
        Blueprint|null $expected,
        Blueprint|null $existing,
        bool           $checkOrder = false,
        array          $sources = [],
    ): Result\StoreDiff
    {
        $name = (string) ($expected?->name ?? $existing?->name);
        $status = match (true) {
            $existing === null => Result\Status::Missing,
            $expected === null => Result\Status::Superfluous,
            default => Result\Status::Equal,
        };

        $diff = new Result\StoreDiff($storageName, $name, $status, $expected, $existing, $sources);

        $this->diffFields($diff, $expected, $existing);
        $this->diffIndexes($diff, $expected, $existing);
        $this->diffForeignKeys($diff, $expected, $existing);

        if ($checkOrder && $expected && $existing) {
            $diff->order = $this->diffOrder($expected, $existing);
        }

        $diff->determineStatus();

        return $diff;
    }

    private function diffFields(Result\StoreDiff $diff, Blueprint|null $expected, Blueprint|null $existing): void
    {
        foreach ($expected?->fields ?? [] as $field) {
            $current = $existing?->fieldByName($field->name);

            $diff->fields[] = $current
                ? $this->fieldComparer->compare($field, $current)
                : Result\FieldDiff::missing($field);
        }

        foreach ($existing?->fields ?? [] as $field) {
            if ($expected?->fieldByName($field->name) === null) {
                $diff->fields[] = Result\FieldDiff::superfluous($field);
            }
        }
    }

    private function diffIndexes(Result\StoreDiff $diff, Blueprint|null $expected, Blueprint|null $existing): void
    {
        foreach ($expected?->indexes ?? [] as $index) {
            $diff->indexes[] = new Result\IndexDiff(
                $existing?->indexByHash($index->hash()) ? Result\Status::Equal : Result\Status::Missing,
                $index,
            );
        }

        foreach ($existing?->indexes ?? [] as $index) {
            if ($expected?->indexByHash($index->hash()) !== null) {
                continue;
            }

            if ($expected !== null && $this->isImpliedByForeignKey($index, $existing)) {
                continue;
            }

            $diff->indexes[] = new Result\IndexDiff(Result\Status::Superfluous, $index);
        }
    }

    // Some databases (MySQL) add an index for every foreign key that has none; such an
    // index belongs to the foreign key and is never declared by the backend itself
    private function isImpliedByForeignKey(Blueprint\Index $index, Blueprint $existing): bool
    {
        if ($index->isPrimary || $index->isUnique || count($index->fields()) !== 1) {
            return false;
        }

        $fieldName = $index->fields()[0]->name;

        return array_any(
            $existing->foreignKeys,
            fn(Blueprint\ForeignKey $foreignKey) => $foreignKey->field === $fieldName
        );
    }

    private function diffForeignKeys(Result\StoreDiff $diff, Blueprint|null $expected, Blueprint|null $existing): void
    {
        foreach ($expected?->foreignKeys ?? [] as $foreignKey) {
            $current = $existing?->foreignKeyByHash($foreignKey->hash());

            if ($current === null) {
                $diff->foreignKeys[] = new Result\ForeignKeyDiff(
                    Result\Status::Missing,
                    $foreignKey
                );

                continue;
            }

            $different = [];

            foreach (['onDelete', 'onUpdate'] as $property) {
                if ($foreignKey->$property !== $current->$property) {
                    $different[] = $property;
                }
            }

            $diff->foreignKeys[] = new Result\ForeignKeyDiff(
                $different ? Result\Status::Changed : Result\Status::Equal,
                $foreignKey,
                $current,
                $different,
            );
        }

        foreach ($existing?->foreignKeys ?? [] as $foreignKey) {
            if ($expected?->foreignKeyByHash($foreignKey->hash()) === null) {
                $diff->foreignKeys[] = new Result\ForeignKeyDiff(
                    Result\Status::Superfluous,
                    existing: $foreignKey
                );
            }
        }
    }

    private function diffOrder(Blueprint $expected, Blueprint $existing): Result\FieldOrderDiff
    {
        $expectedNames = array_map(fn(Blueprint\Field $field) => $field->name, $expected->fields);
        $existingNames = array_map(fn(Blueprint\Field $field) => $field->name, $existing->fields);
        $expectedOrder = array_values(array_intersect($expectedNames, $existingNames));
        $existingOrder = array_values(array_intersect($existingNames, $expectedNames));
        $inPlace = $this->longestCommonSubsequence($expectedOrder, $existingOrder);

        return new Result\FieldOrderDiff(
            $expectedOrder,
            $existingOrder,
            array_values(array_diff($expectedOrder, $inPlace)),
        );
    }

    /**
     * The longest list of names that appears in the same order in both lists: the fields
     * that can stay where they are while the others move around them.
     *
     * @param string[] $a
     * @param string[] $b
     * @return string[]
     */
    private function longestCommonSubsequence(array $a, array $b): array
    {
        $countA = count($a);
        $countB = count($b);

        // $lengths[$i][$j] is the length of the longest common subsequence of a[$i..] and b[$j..]
        $lengths = array_fill(0, $countA + 1, array_fill(0, $countB + 1, 0));

        for ($i = $countA - 1; $i >= 0; $i--) {
            for ($j = $countB - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $subsequence = [];
        $i = 0;
        $j = 0;

        while ($i < $countA && $j < $countB) {
            if ($a[$i] === $b[$j]) {
                $subsequence[] = $a[$i];

                $i++;

                $j++;
            }
            elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $i++;
            }
            else {
                $j++;
            }
        }

        return $subsequence;
    }
}
