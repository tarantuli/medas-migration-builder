<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

use Medas\Core\{Attributes\ConfigValue, Attributes\Service, Interfaces\DirectoryCreator};
use Medas\FileBuilder\{
    PhpClass\MethodDefinition,
    PhpClass\ParameterDefinition,
    PhpClass\PhpClassDefinition,
    PhpClassBuilder
};
use Medas\MigrationBuilder\{BuilderResolver, Structure};
use Medas\StorageManager\{
    ConfigOptions\MigrationsStoreName,
    Migrations\Migration,
    StorageManager,
    Type,
    UnitOfWork\UnitOfWork
};

#[Service]
readonly class MigrationStoreFileBuilder
{
    private const string MAGIC_CLASS_NAME = 'Migration00000000000000000000';

    public function __construct(
        private BuilderResolver  $builderResolver,
        private DirectoryCreator $directoryCreator,
        private PhpClassBuilder  $phpClassBuilder,
        private StorageManager   $storageManager,

        #[ConfigValue(MigrationsStoreName::class)]
        private string           $migrationsStoreName,
    )
    {
    }

    /**
     * Generates the magic migration file that creates the migrations store table,
     * if it does not already exist in the given migrations directory.
     */
    public function ensureFileExists(string $migrationsDirectory): void
    {
        $filePath = $migrationsDirectory . DIRECTORY_SEPARATOR . self::MAGIC_CLASS_NAME . '.php';

        if (file_exists($filePath)) {
            return;
        }

        $this->directoryCreator->create($migrationsDirectory);

        file_put_contents($filePath, $this->buildClassCode());
    }

    private function buildClassCode(): string
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
        $blueprint = $this->buildBlueprint();
        $builder = $this->builderResolver->find($storage);

        $builder->build(
            $storage,
            $blueprint,
            $migrateMethod,
            $undoMethod,
            ignoreExistingStructure: true
        );

        $classDefinition = new PhpClassDefinition(self::MAGIC_CLASS_NAME, 'Medas\\Migrations');

        $classDefinition->implements[] = Migration::class;
        $classDefinition->methods = [$migrateMethod, $undoMethod];

        return $this->phpClassBuilder->build($classDefinition);
    }

    private function buildBlueprint(): Structure\Blueprint
    {
        $blueprint = new Structure\Blueprint();

        $blueprint->name = $this->migrationsStoreName;
        $blueprint->isMigrationStore = true;
        $migrationField = new Structure\Blueprint\Field('migration', Type::Text);
        $migratedAtField = new Structure\Blueprint\Field('migratedAt', Type::DateTime);

        $blueprint->addField($migrationField);
        $blueprint->addField($migratedAtField);
        $blueprint->addIndex(new Structure\Blueprint\Index([$migrationField]));

        return $blueprint;
    }
}
