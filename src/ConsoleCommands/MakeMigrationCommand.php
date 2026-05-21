<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\ConsoleCommands;

use Medas\Console\{Commands\BaseConsoleCommand,
    Commands\CommandInput,
    Commands\ConsoleCommandGroup,
    Commands\Option,
    Formats\SafeColor,
    Printer,
    Text};
use Medas\Core\{Attributes\ConfigValue,
    Attributes\Service,
    Interfaces\ImplementorFinder,
    Interfaces\PackageEntities,
    Interfaces\ServiceManager};
use Medas\EntityManager\ConfigOptions\EntityDirectories;
use Medas\MigrationBuilder\{MigrationFactory, MigrationFactory\Settings};
use Medas\StorageManager\ConfigOptions\MigrationDirectory;

#[Service]
readonly class MakeMigrationCommand extends BaseConsoleCommand
{
    public function __construct(
        private Printer        $consolePrinter,
        #[ConfigValue(EntityDirectories::class)]
        private array     $entityDirectories,
        private MigrationBuilderGroup $group,
        #[ConfigValue(MigrationDirectory::class)    ]
        private string    $migrationDirectory,
        private MigrationFactory      $migrationBuildManager,
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
            $this->entityDirectories,
            $this->migrationDirectory
        );

        if ($input->hasOption('clean')) {
            $settings->ignoreExistingStorage = true;
        }

        $packagesDefiningEntities
            = $this->serviceManager->resolve(ImplementorFinder::class)->find(PackageEntities::class);

        foreach ($packagesDefiningEntities as $packageDefiningEntities) {
            $settings->sourceDirectories = array_merge(
                $settings->sourceDirectories,
                $packageDefiningEntities->directories()
            );
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
