<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

use Medas\Core\Attributes\Service;
use Medas\FileBuilder\{
    PhpClass\MethodDefinition,
    PhpClass\ParameterDefinition,
    PhpClass\PhpClassDefinition,
    PhpClassBuilder
};
use Medas\MigrationBuilder\{BuilderResolver, Structure};
use Medas\StorageManager\{Migrations\Migration, StorageManager, UnitOfWork\UnitOfWork};

#[Service]
readonly class MigrationClassCodeBuilder
{
    public function __construct(
        private BuilderResolver $builderResolver,
        private PhpClassBuilder $phpClassBuilder,
        private StorageManager  $storageManager,
    )
    {
    }

    /**
     * Builds the PHP source for a migration class named $className whose
     * migrate() creates the store described by $blueprint (and undo() drops it).
     * The store is built with ignoreExistingStructure, so it's emitted without
     * diffing against any current schema - which suits the bootstrap system-store
     * migrations that must run before the normal, entity-derived migration flow.
     */
    public function build(string $className, Structure\Blueprint $blueprint): string
    {
        $migrateMethod = new MethodDefinition('migrate');

        $migrateMethod->parameters = [new ParameterDefinition(UnitOfWork::class, 'unitOfWork')];
        $migrateMethod->returnTypes = ['void'];
        $migrateMethod->body = '';
        $undoMethod = new MethodDefinition('undo');

        $undoMethod->parameters = [new ParameterDefinition(UnitOfWork::class, 'unitOfWork')];
        $undoMethod->returnTypes = ['void'];
        $undoMethod->body = '';
        $storage = $this->storageManager->byName();
        $builder = $this->builderResolver->find($storage);

        $builder->build(
            $storage,
            $blueprint,
            $migrateMethod,
            $undoMethod,
            ignoreExistingStructure: true
        );

        $classDefinition = new PhpClassDefinition($className, 'Medas\\Migrations');

        $classDefinition->implements[] = Migration::class;
        $classDefinition->methods = [$migrateMethod, $undoMethod];

        return $this->phpClassBuilder->build($classDefinition);
    }
}
