<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\MigrationFactory;

use Medas\Core\{Attributes\ConfigValue, Attributes\Service, Interfaces\DirectoryCreator};
use Medas\MigrationBuilder\Structure;
use Medas\StorageManager\{
    ConfigOptions\GenerateSequencesStore,
    ConfigOptions\SequencesStoreName,
    Type
};

#[Service]
readonly class SequenceStoreFileBuilder
{
    // Sorts immediately after the migrations-store magic migration (all zeros),
    // so the sequences' table is created right after __migrations exists and is
    // then recorded like any normal migration.
    private const string MAGIC_CLASS_NAME = 'Migration00000000000000000001';

    public function __construct(
        private DirectoryCreator          $directoryCreator,
        private MigrationClassCodeBuilder $migrationClassCodeBuilder,

        #[ConfigValue(SequencesStoreName::class)]
        private string                    $sequencesStoreName,

        #[ConfigValue(GenerateSequencesStore::class)]
        private bool                      $generateSequencesStore,
    )
    {
    }

    /**
     * Generates the migration that creates the sequences' store table, once, and
     * only when the app has opted in via the generate-sequences-store config.
     */
    public function ensureFileExists(string $migrationsDirectory): void
    {
        if (!$this->generateSequencesStore) {
            return;
        }

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

        $blueprint->name = $this->sequencesStoreName;
        $scopeField = new Structure\Blueprint\Field('scope', Type::Text);
        $yearField = new Structure\Blueprint\Field('year', Type::Integer);
        $valueField = new Structure\Blueprint\Field('value', Type::Integer);

        $blueprint->addField($scopeField);
        $blueprint->addField($yearField);
        $blueprint->addField($valueField);

        // Unique on (scope, year): the target of the upsert's ON DUPLICATE KEY,
        // and the guarantee of a single row per sequence.
        $blueprint->addIndex(new Structure\Blueprint\Index([$scopeField, $yearField], isUnique: true));

        return $blueprint;
    }
}
