<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

class Settings
{
    public bool $ignoreExistingStorage = false;

    public function __construct(
        public array  $sourceDirectories,
        public string $migrationsDirectory,
    )
    {
    }
}
