<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure;

use Medas\Core\{
    Attributes\Service,
    Interfaces\Type,
    Types\Binary,
    Types\Boolean,
    Types\Collection,
    Types\DateTime,
    Types\FloatingPoint,
    Types\Integer,
    Types\Relation,
    Types\Text
};
use Medas\StorageManager\Exceptions\{UnhandledType, UnhandledTypeString};

#[Service]
readonly class TypeHandlerResolver
{
    public function __construct(
        private TypeHandlers\BinaryHandler     $binaryHandler,
        private TypeHandlers\BooleanHandler    $booleanHandler,
        private TypeHandlers\CollectionHandler $collectionHandler,
        private TypeHandlers\DateTimeHandler   $dateTimeHandler,
        private TypeHandlers\FloatHandler      $floatHandler,
        private TypeHandlers\IntegerHandler    $integerHandler,
        private TypeHandlers\RelationHandler   $relationHandler,
        private TypeHandlers\TextHandler       $textHandler,
    )
    {
    }

    public function for(Type $type): TypeHandlers\TypeHandler
    {
        // The order matters, don't sort by name
        return match (true) {
            $type instanceof Text => $this->textHandler,
            $type instanceof Binary => $this->binaryHandler,
            $type instanceof Boolean => $this->booleanHandler,
            $type instanceof Collection => $this->collectionHandler,
            $type instanceof DateTime => $this->dateTimeHandler,
            $type instanceof FloatingPoint => $this->floatHandler,
            $type instanceof Integer => $this->integerHandler,
            $type instanceof Relation => $this->relationHandler,
            default => throw new UnhandledType($type),
        };
    }

    public function forString(string $type): TypeHandlers\TypeHandler
    {
        return match (true) {
            $type === 'bool' => $this->booleanHandler,
            $type === 'int' => $this->integerHandler,
            class_exists($type) => $this->relationHandler,
            default => throw new UnhandledTypeString($type),
        };
    }
}
