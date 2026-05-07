<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Blueprint;

use Medas\EntityManager\Attributes\Relations\Action;

class ForeignKey
{
    public function __construct(
        public string $field,
        public string $foreignEntity,
        public string $foreignField,
        public Action $onDelete,
        public Action $onUpdate,
    )
    {
    }

    public function hash(): string
    {
        return $this->field . "\0" . $this->foreignEntity . "\0" . $this->foreignField;
    }
}
