<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Changes;

use Medas\MigrationBuilder\Structure\Blueprint;

class Job
{
    public function __construct(
        public Blueprint $expected,
        public Blueprint $existing,
        public Changes   $changes,
        public bool      $foundChanges = false,
    )
    {
    }
}
