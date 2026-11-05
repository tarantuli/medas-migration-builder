<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\ConsoleCommands;

use Medas\Console\{
    Commands\BaseConsoleCommand,
    Commands\CommandInput,
    Commands\ConsoleCommandGroup,
    Commands\Option,
    Formats\SafeColor,
    Printer,
    Text
};
use Medas\Core\Attributes\{ConfigValue, Service};
use Medas\MigrationBuilder\{
    MigrationFactory,
    MigrationFactory\Settings,
    MigrationFactory\SourceDirectoryFinder
};
use Medas\StorageManager\ConfigOptions\MigrationDirectory;

#[Service]
readonly class MakeMigrationCommand extends BaseConsoleCommand
{
    public function __construct(
        private MigrationBuilderGroup $group,
        private Printer               $consolePrinter,

        #[ConfigValue(MigrationDirectory::class)]
        private string                $migrationDirectory,
        private MigrationFactory      $migrationBuildManager,
        private SourceDirectoryFinder $sourceDirectoryFinder,
    )
    {
    }

    public function group(): ConsoleCommandGroup
    {
        return $this->group;
    }

    public function name(): string
    {
        return 'make-migration';
    }

    public function aliases(): array
    {
        return ['c.migration'];
    }

    public function description(): string
    {
        return 'Makes a new migration class file';
    }

    public function options(): array
    {
        return [new Option('clean')];
    }

    public function process(CommandInput $input): void
    {
        $settings = new Settings($this->sourceDirectoryFinder->find(), $this->migrationDirectory);

        if ($input->hasOption('clean')) {
            $settings->ignoreExistingStorage = true;
        }

        $filePath = $this->migrationBuildManager->createMigration($settings);

        $this->consolePrinter->printEol();

        $filePath
            ? $this->consolePrinter->print(
                new Text('created migration file '),
                new Text($filePath, SafeColor::LightYellow)
            )
            : $this->consolePrinter->print(new Text('no need to create a migration file', SafeColor::LightGray));
    }
}
