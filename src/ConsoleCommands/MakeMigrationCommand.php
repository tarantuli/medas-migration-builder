<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\ConsoleCommands;

use Medas\ConfigOptions\OptionController;
use Medas\Console\{
    Commands\BaseConsoleCommand,
    Commands\CommandInput,
    Commands\ConsoleCommandGroup,
    Commands\Option,
    Formats\Color,
    Text
};
use Medas\ConsolePrinter\ConsolePrinter;
use Medas\Core\{Attributes\Service, Interfaces\ImplementorFinder, Interfaces\ServiceManager};
use Medas\EntityManager\ConfigOptions\EntityDirectories;
use Medas\MigrationBuilder\{MigrationFactory, MigrationFactory\Settings};
use Medas\StorageManager\{ConfigOptions\MigrationDirectory, Interfaces\PackageEntities};

#[Service]
readonly class MakeMigrationCommand extends BaseConsoleCommand
{
    public function __construct(
        private ConsolePrinter        $consolePrinter,
        private EntityDirectories     $entityDirectories,
        private MigrationBuilderGroup $group,
        private MigrationDirectory    $migrationDirectory,
        private MigrationFactory      $migrationBuildManager,
        private OptionController      $optionController,
        private ServiceManager        $serviceManager,
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
        $settings = new Settings(
            $this->optionController->getValue($this->entityDirectories),
            $this->optionController->getValue($this->migrationDirectory)
        );

        if ($input->hasOption('clean')) {
            $settings->ignoreExistingStorage = true;
        }

        foreach ($this->serviceManager->resolve(ImplementorFinder::class)->find(PackageEntities::class) as $packageEntities) {
            $settings->sourceDirectories = array_merge(
                $settings->sourceDirectories,
                $packageEntities->directories()
            );
        }

        $filePath = $this->migrationBuildManager->createMigration($settings);

        $this->consolePrinter->printEol();

        $filePath
            ? $this->consolePrinter->print(
                new Text('created migration file '),
                new Text($filePath, Color::LightYellow)
            )
            : $this->consolePrinter->print(new Text('no need to create a migration file', Color::LightGray));
    }
}
