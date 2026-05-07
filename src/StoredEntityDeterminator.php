<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\EntityManager\{Attributes\Entity, MetaData\EntityCompiler};

#[Service]
readonly class StoredEntityDeterminator
{
    public function __construct(
        private EntityCompiler $entityCompiler,
    )
    {
    }

    public function determine(string $className, array $directories): Entity|null
    {
        $class = new \ReflectionClass($className);

        if (!$class->getFileName()) {
            return null;
        }

        $foundDirectory = false;

        foreach ($directories as $directory) {
            if (str_starts_with($class->getFileName(), $directory)) {
                $foundDirectory = true;

                break;
            }
        }

        if (!$foundDirectory) {
            return null;
        }

        if (!$entity = $this->entityCompiler->compile($class)) {
            return null;
        }

        if ($entity->storage === null && $entity->store === null) {
            return null;
        }

        return $entity;
    }
}
