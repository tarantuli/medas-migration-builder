<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\StorageManager\{
    Inheritance\LinkingStore,
    Inheritance\OriginalClassStorageStrategy,
    Interfaces\Storage,
    UnitOfWork\ActionSet
};

#[Service]
readonly class OriginalClassStorageStrategyActionBuilder
{
    public function __construct(
        private OriginalClassStorageActionBuilders\LinkingStoreActionBuilder $forLinkingStore,
    )
    {
    }

    public function build(
        OriginalClassStorageStrategy $strategy,
        Structure\Blueprint          $blueprint,
        Storage                      $storage,
        bool                         $ignoreExistingStructure = false
    ): ActionSet
    {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($strategy instanceof LinkingStore) {
            return $this->forLinkingStore->buildStoreActions(
                $blueprint,
                $storage,
                $ignoreExistingStructure
            );
        }

        throw new Exceptions\UnsupportedStrategy($strategy);
    }
}
