<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Changes;

use Medas\MigrationBuilder\Structure\Blueprint\{Field, ForeignKey, Index};

class Changes
{
    /** @var Field[] */
    public array $addFields = [];

    /** @var Field[] */
    public array $changeFields = [];

    /** @var Index[] */
    public array $addIndexes = [];

    /** @var ForeignKey[] */
    public array $changeForeignKey = [];

    /** @var ForeignKey[] */
    public array $addForeignKey = [];

    public function __construct(public string $name)
    {
    }
}
