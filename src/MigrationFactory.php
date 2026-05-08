<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{Attributes\Service, Interfaces\DirectoryCreator};
use Medas\EntityManager\Attributes\Entity;
use Medas\FileBuilder\{
    PhpClass\MethodDefinition,
    PhpClass\ParameterDefinition,
    PhpClass\PhpClassDefinition,
    PhpClassBuilder
};
use Medas\StorageManager\{Migrations\Migration, StorageManager, UnitOfWork\UnitOfWork};

#[Service]
readonly class MigrationFactory
{
    public function __construct(
        private ActionGatherer                   $actionGatherer,
        private BuilderResolver                  $builderResolver,
        private DirectoryCreator                 $directoryCreator,
        private PhpClassBuilder                  $phpClassBuilder,
        private StorageManager                   $storageManager,
        private Structure\EntityBlueprintBuilder $entityBlueprintBuilder,
    )
    {
    }

    public function createMigration(MigrationFactory\Settings $settings): string|null
    {
        $job = $this->createMigrationClassCode($settings);

        if ($job->migrationNeeded) {
            $this->directoryCreator->create($settings->migrationsDirectory);

            $filePath = $settings->migrationsDirectory
                . DIRECTORY_SEPARATOR
                . $job->className
                . '.php';

            file_put_contents($filePath, $job->classCode);

            return $filePath;
        }

        return null;
    }

    public function createMigrationClassCode(MigrationFactory\Settings $settings): MigrationFactory\Job
    {
        $job = new MigrationFactory\Job($settings);

        foreach ($job->settings->sourceDirectories as $i => $directory) {
            $job->settings->sourceDirectories[$i] = realpath($directory);
        }

        $this->initializeClass($job);
        $this->initializeMethods($job);
        $this->processEntities($job);

        $job->classCode = $job->migrationNeeded
            ? $this->phpClassBuilder->build($job->migrationClass)
            : null;

        return $job;
    }

    private function initializeClass(MigrationFactory\Job $job): void
    {
        $now = new \DateTime()->format('YmdHisu');
        $job->className = 'Migration' . $now;
        $job->migrationClass = new PhpClassDefinition($job->className, 'Medas\\Migrations');
        $job->migrationClass->implements[] = Migration::class;
    }

    private function initializeMethods(MigrationFactory\Job $job): void
    {
        $this->initializeMigrateMethod($job);
        $this->initializeUndoMethod($job);

        $job->migrationClass->methods = [$job->migrateMethod, $job->undoMethod];
    }

    private function initializeMigrateMethod(MigrationFactory\Job $job): void
    {
        $job->migrateMethod = new MethodDefinition('migrate');
        $job->migrateMethod->parameters = [new ParameterDefinition(UnitOfWork::class, 'unitOfWork')];
        $job->migrateMethod->returnTypes = ['void'];
        $job->migrateMethod->body = '';
    }

    private function initializeUndoMethod(MigrationFactory\Job $job): void
    {
        $job->undoMethod = new MethodDefinition('undo');
        $job->undoMethod->parameters = [new ParameterDefinition(UnitOfWork::class, 'unitOfWork')];
        $job->undoMethod->returnTypes = ['void'];
        $job->undoMethod->body = '';
    }

    private function processEntities(MigrationFactory\Job $job): void
    {
        $job->migrationNeeded = false;

        foreach ($this->actionGatherer->scan($job->settings->sourceDirectories) as $className => $entity) {
            $this->processEntity($job, $className, $entity);
        }
    }

    private function processEntity(MigrationFactory\Job $job, string $className, Entity $entity): void
    {
        $storage = $this->storageManager->byName($entity->storage);
        $expectedStructure = $this->entityBlueprintBuilder->find($className);
        $needed = $this->builderResolver->for($storage)
            ->build(
                $storage,
                $expectedStructure,
                $job->migrateMethod,
                $job->undoMethod,
                $job->settings->ignoreExistingStorage,
            );

        $job->migrationNeeded = $job->migrationNeeded || $needed;
    }
}
