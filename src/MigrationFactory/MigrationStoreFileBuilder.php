<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

use Medas\Core\{Attributes\ConfigValue, Attributes\Service, Interfaces\DirectoryCreator};
use Medas\MigrationBuilder\Structure;
use Medas\StorageManager\{ConfigOptions\MigrationsStoreName, Type};

#[Service]
readonly class MigrationStoreFileBuilder
{
    private const string MAGIC_CLASS_NAME = 'Migration00000000000000000000';

    public function __construct(
        private DirectoryCreator          $directoryCreator,
        private MigrationClassCodeBuilder $migrationClassCodeBuilder,

        #[ConfigValue(MigrationsStoreName::class)]
        private string                    $migrationsStoreName,
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

        file_put_contents(
            $filePath,
            $this->migrationClassCodeBuilder->build(self::MAGIC_CLASS_NAME, $this->buildBlueprint())
        );
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
