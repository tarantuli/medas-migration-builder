<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Changes;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\StorageManager\Type;

#[Service]
readonly class ChangeFinder
{
    public function find(Blueprint $expected, Blueprint $existing): Changes|null
    {
        $job = new Job($expected, $existing, new Changes($expected->name));

        $this->checkFields($job);
        $this->checkIndexes($job);
        $this->checkForeignKeys($job);

        return $job->foundChanges ? $job->changes : null;
    }

    private function checkFields(Job $job): void
    {
        foreach ($job->expected->fields as $field) {
            if ($field->store !== null && $field->store !== $job->expected->name) {
                continue;
            }

            if ($current = $job->existing->fieldByName($field->name)) {
                if ($this->areFieldsComparable($field, $current)) {
                    continue;
                }

                $job->changes->changeFields[] = $field;
            }
            else {
                $job->changes->addFields[] = $field;
            }

            $job->foundChanges = true;
        }
    }

    private function areFieldsComparable(Blueprint\Field $field, Blueprint\Field $current): bool
    {
        $diff = array_udiff_assoc((array) $field, (array) $current, fn($a, $b) => $a <=> $b);

        if (isset($diff['store'])) {
            unset($diff['store']);
        }

        if (isset($diff['isIndex'])) {
            unset($diff['isIndex']);
        }

        // If the diff contains length or value parameters, compare ranges
        if (isset($diff['minLength']) && $current->minLength <= $field->minLength) {
            unset($diff['minLength']);
        }

        if (isset($diff['maxLength']) && $current->maxLength >= $field->maxLength) {
            unset($diff['maxLength']);
        }

        if (isset($diff['minValue']) && $current->minValue <= $field->minValue) {
            unset($diff['minValue']);
        }

        if (isset($diff['maxValue']) && $current->maxValue >= $field->maxValue) {
            unset($diff['maxValue']);
        }

        foreach ($diff as $key => $value) {
            $fieldValue = $field->$key;
            $currentValue = $current->$key;

            if ($fieldValue === Type::Boolean && $currentValue === Type::Integer) {
                unset($diff[$key]);

                continue;
            }

            if (is_object($value) && enum_exists($value::class)) {
                // Compare by backed value
                if ($fieldValue->value == $currentValue) {
                    unset($diff[$key]);

                    continue;
                }
            }

            if ($current->hasDefault === false && $field->hasDefault === true && $field->default === null) {
                unset($diff[$key]);

                /** @noinspection PhpUnnecessaryStopStatementInspection */
                continue;
            }
        }

        return $diff === [];
    }

    private function checkIndexes(Job $job): void
    {
        foreach ($job->expected->indexes as $index) {
            if ($index->isPrimary) {
                continue;
            }

            if (!$job->existing->indexByHash($index->hash())) {
                $job->changes->addIndexes[] = $index;
                $job->foundChanges = true;
            }
        }
    }

    private function checkForeignKeys(Job $job): void
    {
        foreach ($job->expected->foreignKeys as $foreignKey) {
            if ($current = $job->existing->foreignKeyByHash($foreignKey->hash())) {
                if ($this->areForeignKeysComparable($foreignKey, $current)) {
                    continue;
                }

                $job->changes->changeForeignKey[] = $foreignKey;
            }
            else {
                $job->changes->addForeignKey[] = $foreignKey;
            }

            $job->foundChanges = true;
        }
    }

    private function areForeignKeysComparable(Blueprint\ForeignKey $foreignKey, Blueprint\ForeignKey $current): bool
    {
        return $foreignKey->onDelete === $current->onDelete
            && $foreignKey->onUpdate === $current->onUpdate;
    }
}
