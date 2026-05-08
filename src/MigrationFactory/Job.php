<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

use Medas\FileBuilder\PhpClass\{MethodDefinition, PhpClassDefinition};

class Job
{
    public string $className;
    public string|null $classCode;
    public bool $migrationNeeded;
    public PhpClassDefinition $migrationClass;
    public MethodDefinition $migrateMethod;
    public MethodDefinition $undoMethod;

    public function __construct(
        public Settings $settings,
    )
    {
    }
}
