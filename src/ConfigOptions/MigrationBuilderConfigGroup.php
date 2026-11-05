<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\ConfigOptions;

use Medas\Core\{Attributes\Service, Interfaces\ConfigGroup};

#[Service]
readonly class MigrationBuilderConfigGroup implements ConfigGroup
{
    public function parent(): ConfigGroup|null
    {
        return null;
    }

    public function name(): string
    {
        return 'migration-builder';
    }
}
