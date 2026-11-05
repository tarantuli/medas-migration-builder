<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\Blueprint\Field;
use Medas\StorageManager\Type;

/**
 * Compares an expected field with the one found in a storage. Differences the backend
 * can live with, such as a column that allows a wider range than the entity needs, are
 * reported as compatible; migrations only act on incompatible ones.
 */
#[Service]
readonly class FieldComparer
{
    // Properties that say nothing about the field itself: the store is where the field
    // is declared, and single field indexes are compared as indexes
    private const array IGNORED_PROPERTIES = ['store', 'isIndex'];

    public function compare(Field $expected, Field $existing): Result\FieldDiff
    {
        $different = array_keys(array_udiff_assoc((array) $expected, (array) $existing, fn($a, $b) => $a <=> $b));
        $incompatible = [];
        $compatible = [];

        foreach ($different as $property) {
            if (in_array($property, self::IGNORED_PROPERTIES, true)) {
                continue;
            }

            $severity = $this->propertySeverity($property, $expected, $existing);

            if ($severity === Result\Severity::Incompatible) {
                $incompatible[] = $property;
            }
            elseif ($severity === Result\Severity::Compatible) {
                $compatible[] = $property;
            }
        }

        $severity = match (true) {
            $incompatible !== [] => Result\Severity::Incompatible,
            $compatible !== [] => Result\Severity::Compatible,
            default => Result\Severity::Equal,
        };

        return new Result\FieldDiff(
            $expected->name,
            $severity === Result\Severity::Equal ? Result\Status::Equal : Result\Status::Changed,
            $expected,
            $existing,
            $severity,
            $incompatible,
            $compatible,
        );
    }

    private function propertySeverity(string $property, Field $expected, Field $existing): Result\Severity
    {
        $expectedValue = $expected->$property;
        $existingValue = $existing->$property;

        // A storage that allows a wider range than needed is fine
        $isWider = match ($property) {
            'minLength' => $existing->minLength <= $expected->minLength,
            'maxLength' => $existing->maxLength >= $expected->maxLength,
            'minValue' => $existing->minValue <= $expected->minValue,
            'maxValue' => $existing->maxValue >= $expected->maxValue,
            default => false,
        };

        if ($isWider) {
            return Result\Severity::Compatible;
        }

        // Storages without a boolean type keep booleans as integers
        if ($expectedValue === Type::Boolean && $existingValue === Type::Integer) {
            return Result\Severity::Compatible;
        }

        // An enum is stored as its backed value
        if ($expectedValue instanceof \BackedEnum && $expectedValue->value == $existingValue) {
            return Result\Severity::Equal;
        }

        // A default of null is the same as no default
        if ($property === 'hasDefault'
                && $existing->hasDefault === false
                && $expected->hasDefault === true
                && $expected->default === null) {
            return Result\Severity::Equal;
        }

        return Result\Severity::Incompatible;
    }
}
