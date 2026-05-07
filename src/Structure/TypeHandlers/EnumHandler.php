<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\TypeHandlers;

use Medas\Core\{Attributes\Service, Interfaces\Type, Types\Integer, Types\Text};
use Medas\MigrationBuilder\Exceptions\UnhandledEnumBlueprintType;
use Medas\StorageManager\{
    Exceptions\EnumIsNotBacked,
    Exceptions\UnhandledEnumBackingType,
    Type as BlueprintType
};

#[Service]
readonly class EnumHandler
{
    /**
     * Returns a pseudo Type object that covers the backed cases of the enumeration
     */
    public function getPseudoType(string $enum): Type
    {
        return match ($this->getBlueprintType($enum)) {
            BlueprintType::Integer => $this->getIntegerType($enum),
            BlueprintType::Text => $this->getStringType($enum),
            default => throw new UnhandledEnumBlueprintType($enum, $this->getBlueprintType($enum))
        };
    }

    private function getIntegerType(string $enum): Type
    {
        $minValue = null;
        $maxValue = null;

        foreach (new \ReflectionEnum($enum)->getCases() as $case) {
            $value = $case->getBackingValue();

            if ($minValue === null || $value < $minValue) {
                $minValue = $value;
            }

            if ($maxValue === null || $value > $maxValue) {
                $maxValue = $value;
            }
        }

        return new Integer($minValue, $maxValue);
    }

    private function getStringType(string $enum): Type
    {
        $minLength = null;
        $maxLength = null;

        foreach (new \ReflectionEnum($enum)->getCases() as $case) {
            $length = strlen($case->getBackingValue());

            if ($minLength === null || $length < $minLength) {
                $minLength = $length;
            }

            if ($maxLength === null || $length > $maxLength) {
                $maxLength = $length;
            }
        }

        return new Text($minLength, $maxLength);
    }

    public function getBlueprintType(string $enum): BlueprintType
    {
        $enumReflection = new \ReflectionEnum($enum);

        if (!$enumReflection->isBacked()) {
            throw new EnumIsNotBacked($enum);
        }

        return match ($enumReflection->getBackingType()->getName()) {
            'int' => BlueprintType::Integer,
            'string' => BlueprintType::Text,
            default => throw new UnhandledEnumBackingType($enum, $enumReflection->getBackingType())
        };
    }
}
