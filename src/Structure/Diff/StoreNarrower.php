<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\Attributes\Service;
use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\Type;

/**
 * Narrows an entity's blueprint down to the store a relational driver creates for it,
 * following the same rules as the create table builders:
 *
 * - fields declared on a parent class live in the parent's store, except for the primary
 *   key, which the child's store repeats (not generated) with a foreign key to the parent
 * - indexes over a parent's fields belong to the parent, unless the field is the primary key
 * - foreign keys declared on a parent class belong to the parent
 * - collection fields have no column; they are returned separately
 *
 * The given blueprint is never changed, as blueprints are cached.
 */
#[Service]
readonly class StoreNarrower
{
    public function narrow(Blueprint $blueprint): NarrowedStore
    {
        $narrowed = new Blueprint();

        $narrowed->name = $blueprint->name;
        $narrowed->parent = $blueprint->parent;
        $narrowed->isMigrationStore = $blueprint->isMigrationStore;
        $narrowed->storeOriginalClass = $blueprint->storeOriginalClass;

        if (isset($blueprint->storeRequestingOriginalClassStorage)) {
            $narrowed->storeRequestingOriginalClassStorage = $blueprint->storeRequestingOriginalClassStorage;
        }

        if (isset($blueprint->originalEntityClassStorageStrategy)) {
            $narrowed->originalEntityClassStorageStrategy = $blueprint->originalEntityClassStorageStrategy;
        }

        $result = new NarrowedStore($narrowed);
        $primaryFields = $blueprint->primaryIndex()?->fields() ?? [];

        // Clones of the fields that live in this store, keyed by name
        $fields = [];

        foreach ($blueprint->fields as $field) {
            $isPrimary = in_array($field, $primaryFields, true);

            if ($result->isStoredElsewhere($field->store) && !$isPrimary) {
                continue;
            }

            if ($field->type === Type::Collection) {
                if (!$result->isStoredElsewhere($field->store)) {
                    $result->collectionFields[] = $field;
                }

                continue;
            }

            $clone = clone $field;

            if ($result->isStoredElsewhere($field->store)) {
                // The inherited primary key: a plain copy of the parent's key
                $clone->isGenerated = false;

                $foreignKey = new Blueprint\ForeignKey(
                    $field->name,
                    $field->store,
                    $field->name,
                    Action::Cascade,
                    Action::Cascade,
                    $blueprint->name,
                );

                $result->addedForeignKeyHashes[] = $foreignKey->hash();

                $narrowed->addForeignKey($foreignKey);
            }

            $fields[$field->name] = $clone;

            $narrowed->addField($clone);
        }

        $this->addIndexes($blueprint, $result, $fields, $primaryFields);
        $this->addForeignKeys($blueprint, $result);

        return $result;
    }

    /**
     * @param Blueprint\Field[] $fields
     * @param Blueprint\Field[] $primaryFields
     */
    private function addIndexes(
        Blueprint     $blueprint,
        NarrowedStore $result,
        array         $fields,
        array         $primaryFields
    ): void
    {
        foreach ($blueprint->indexes as $index) {
            $narrowedIndex = new Blueprint\Index([], $index->isPrimary, $index->isUnique);

            foreach ($index->fields() as $field) {
                $isPrimary = in_array($field, $primaryFields, true);

                if (($result->isStoredElsewhere($field->store) && !$isPrimary) || !isset($fields[$field->name])) {
                    continue 2;
                }

                $narrowedIndex->addField($fields[$field->name]);
            }

            $result->blueprint->addIndex($narrowedIndex);
        }
    }

    private function addForeignKeys(Blueprint $blueprint, NarrowedStore $result): void
    {
        foreach ($blueprint->foreignKeys as $foreignKey) {
            if ($result->isStoredElsewhere($foreignKey->store)) {
                continue;
            }

            $result->blueprint->addForeignKey(clone $foreignKey);
        }
    }
}
