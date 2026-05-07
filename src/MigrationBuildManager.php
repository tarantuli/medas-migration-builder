<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\{Attributes\Service, Interfaces\DirectoryCreator, Interfaces\FileLoader};
use Medas\EntityManager\Attributes\Entity;
use Medas\FileBuilder\{
    PhpClass\MethodDefinition,
    PhpClass\ParameterDefinition,
    PhpClass\PhpClassDefinition,
    PhpClassBuilder
};
use Medas\StorageManager\{Migrations\Migration, StorageManager, UnitOfWork\UnitOfWork};

#[Service]
readonly class MigrationBuildManager
{
    public function __construct(
        private DirectoryCreator                $directoryCreator,
        private FileLoader                      $fileLoader,
        private PhpClassBuilder                 $phpClassBuilder,
        private StorageManager                  $storageManager,
        private StoredEntityDeterminator        $storedEntityDeterminator,
        private Structure\EntityStructureFinder $entityStructureFinder,
    )
    {
    }

    public function createMigration(Settings $settings): string|null
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

    public function createMigrationClassCode(Settings $settings): Job
    {
        $job = new Job($settings);

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

    private function initializeClass(Job $job): void
    {
        $now = new \DateTime()->format('YmdHisu');
        $job->className = 'Migration' . $now;
        $job->migrationClass = new PhpClassDefinition($job->className, 'Medas\\Migrations');
        $job->migrationClass->implements[] = Migration::class;
    }

    private function initializeMethods(Job $job): void
    {
        $this->initializeMigrateMethod($job);
        $this->initializeUndoMethod($job);

        $job->migrationClass->methods = [$job->migrateMethod, $job->undoMethod];
    }

    private function initializeMigrateMethod(Job $job): void
    {
        $job->migrateMethod = new MethodDefinition('migrate');
        $job->migrateMethod->parameters = [new ParameterDefinition(UnitOfWork::class, 'unitOfWork')];
        $job->migrateMethod->returnTypes = ['void'];
        $job->migrateMethod->body = '';
    }

    private function initializeUndoMethod(Job $job): void
    {
        $job->undoMethod = new MethodDefinition('undo');
        $job->undoMethod->parameters = [new ParameterDefinition(UnitOfWork::class, 'unitOfWork')];
        $job->undoMethod->returnTypes = ['void'];
        $job->undoMethod->body = '';
    }

    private function processEntities(Job $job): void
    {
        $job->migrationNeeded = false;

        foreach ($job->settings->sourceDirectories as $directory) {
            $this->fileLoader->load($directory);
        }

        foreach (get_declared_classes() as $className) {
            if (null === $entity = $this->storedEntityDeterminator->determine(
                $className,
                $job->settings->sourceDirectories
            )) {
                continue;
            }

            $this->processEntity($job, $className, $entity);
        }
    }

    private function processEntity(Job $job, string $className, Entity $entity): void
    {
        $expectedStructure = $this->entityStructureFinder->find($className);
        $needed = $this->storageManager->controller($entity->storage)->migrationBuilder()
            ->build(
                $this->storageManager->byName($entity->storage),
                $expectedStructure,
                $job->migrateMethod,
                $job->undoMethod,
                $job->settings->ignoreExistingStorage,
            );

        $job->migrationNeeded = $job->migrationNeeded || $needed;
    }
}
