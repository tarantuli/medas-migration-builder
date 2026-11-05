<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\ConsoleCommands;

use Medas\Console\Commands\{BaseConsoleCommand, CommandInput, ConsoleCommandGroup, Option};
use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\{MigrationFactory\SourceDirectoryFinder, Structure\Diff};

#[Service]
readonly class StorageDiffCommand extends BaseConsoleCommand
{
    public function __construct(
        private Diff\DiffPrinter      $diffPrinter,
        private Diff\StructureDiffer  $structureDiffer,
        private MigrationBuilderGroup $group,
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
        return 'storage-diff';
    }

    public function aliases(): array
    {
        return ['storage-diff'];
    }

    public function description(): string
    {
        return 'Compares the storages with everything the backend expects to find in them';
    }

    public function options(): array
    {
        return [
            Option::noValue('order', 'o', 'Also compare the order of the fields'),
            Option::valueRequired('storage', null, 'Only compare the storage with this name'),
            Option::valueRequired(
                'store',
                null,
                'Only compare stores matching this pattern, e.g. "order*"'
            ),
            Option::valueRequired(
                'ignore',
                null,
                'Comma separated store patterns never reported as superfluous'
            ),
            Option::noValue('only-problems', 'p', 'Leave out differences the backend can live with'),
            Option::noValue(
                'fail',
                null,
                'Exit with code 1 when a migration is needed or stores are superfluous'
            ),
        ];
    }

    public function process(CommandInput $input): void
    {
        $ignore = $input->hasOption('ignore')
            ? array_filter(array_map('trim', explode(',', (string) $input->getOption('ignore'))))
            : [];

        $settings = new Diff\Settings(
            $this->sourceDirectoryFinder->find(),
            $input->hasOption('order'),
            $input->hasOption('storage') ? (string) $input->getOption('storage') : null,
            $input->hasOption('store') ? (string) $input->getOption('store') : null,
            array_values($ignore),
        );

        $diff = $this->structureDiffer->diff($settings);

        $this->diffPrinter->print($diff, $input->hasOption('only-problems'));

        // Commands have no exit code of their own, so a failing check ends the process
        if ($input->hasOption('fail') && $diff->hasDifferences()) {
            exit(1);
        }
    }
}
