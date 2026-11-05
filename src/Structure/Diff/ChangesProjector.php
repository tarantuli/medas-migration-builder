<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\{Blueprint, Changes\Changes};

/**
 * Projects a store's diff onto the changes a migration makes: what is missing gets added,
 * what is incompatibly different gets changed. Superfluous parts, compatible differences
 * and the field order are left alone, as is everything that belongs to a parent's store.
 */
#[Service]
readonly class ChangesProjector
{
    public function project(Result\StoreDiff $diff, NarrowedStore $store): Changes|null
    {
        $changes = new Changes($store->name());

        foreach ($diff->fields as $field) {
            if ($field->expected === null || $store->isStoredElsewhere($field->expected->store)) {
                continue;
            }

            if ($field->status === Result\Status::Missing) {
                $changes->addFields[] = $field->expected;
            }
            elseif ($field->severity === Result\Severity::Incompatible) {
                $changes->changeFields[] = $field->expected;
            }
        }

        // Collection fields have no column, so they're always passed on: the migration
        // builders turn them into their join tables, which are compared there
        foreach ($store->collectionFields as $field) {
            $changes->addFields[] = $field;
        }

        foreach ($diff->indexesWithStatus(Result\Status::Missing) as $index) {
            if ($index->index->isPrimary || $this->hasInheritedField($index->index, $store)) {
                continue;
            }

            $changes->addIndexes[] = $index->index;
        }

        foreach ($diff->foreignKeysWithStatus(Result\Status::Missing, Result\Status::Changed) as $foreignKey) {
            if (in_array($foreignKey->expected->hash(), $store->addedForeignKeyHashes, true)) {
                continue;
            }

            if ($foreignKey->status === Result\Status::Missing) {
                $changes->addForeignKey[] = $foreignKey->expected;
            }
            else {
                $changes->changeForeignKey[] = $foreignKey->expected;
            }
        }

        $hasChanges = $changes->addFields
            || $changes->changeFields
            || $changes->addIndexes
            || $changes->addForeignKey
            || $changes->changeForeignKey;

        return $hasChanges ? $changes : null;
    }

    private function hasInheritedField(Blueprint\Index $index, NarrowedStore $store): bool
    {
        return array_any(
            $index->fields(),
            fn(Blueprint\Field $field) => $store->isStoredElsewhere($field->store)
        );
    }
}
