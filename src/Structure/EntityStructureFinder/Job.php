<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\EntityStructureFinder;

use Medas\EntityManager\MetaData;
use Medas\MigrationBuilder\Structure\Blueprint;

readonly class Job
{
    public function __construct(
        public MetaData  $metaData,
        public Blueprint $blueprint,
    )
    {
    }
}
