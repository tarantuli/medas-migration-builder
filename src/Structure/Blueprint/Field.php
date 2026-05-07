<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Blueprint;

use Medas\Core\Types\Integer;
use Medas\StorageManager\Type;

class Field
{
    public function __construct(
        public string      $name,
        public Type        $type,
        public string|null $store = null,
        public string|null $collectionStore = null,
        public Field|null  $collectionField = null,
        public bool        $isNullable = false,
        public bool        $isUnique = false,
        public bool        $isIndex = false,
        public bool        $isGenerated = false,
        public bool        $isCreationTimestamp = false,
        public bool        $isModificationTimestamp = false,
        public bool        $hasDefault = false,
        public mixed       $default = null,
        public int|float   $minValue = 0,
        public int|float   $maxValue = Integer::UNSIGNED_4_BYTE_MAX,
        public int         $minLength = 0,
        public int         $maxLength = Integer::UNSIGNED_1_BYTE_MAX,
    )
    {
    }
}
