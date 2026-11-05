<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

/**
 * The order of the fields both sides have in common. Fields only one side has are left
 * out, so a single missing field doesn't make every field after it look out of place.
 */
readonly class FieldOrderDiff
{
    /**
     * @param string[] $expectedOrder The common fields in the order the backend expects them
     * @param string[] $existingOrder The common fields in the order the storage has them
     * @param string[] $movedFields   The smallest set of fields that need to move to get the expected order
     */
    public function __construct(
        public array $expectedOrder,
        public array $existingOrder,
        public array $movedFields,
    )
    {
    }

    public function isEqual(): bool
    {
        return $this->movedFields === [];
    }
}
