<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\OriginalClassStorageStrategyActionBuilder;

use Medas\Core\Attributes\{ConfigValue, Service};
use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\{MigrationBuilderManager, Structure\Blueprint};
use Medas\StorageManager\ConfigOptions\OriginalClassStorage\LinkingStore\StoreNamingStrategy;
use Medas\StorageManager\Inheritance\LinkingStore\NamingStrategy;
use Medas\StorageManager\Interfaces\Storage;
use Medas\StorageManager\Type;
use Medas\StorageManager\UnitOfWork\ActionSet;

#[Service]
readonly class ForLinkingStore
{
    public function __construct(
        #[ConfigValue(StoreNamingStrategy::class)]
        private NamingStrategy          $namingStrategy,
        private MigrationBuilderManager $migrationBuilderManager,
    )
    {
    }

    public function buildStoreActions(Blueprint $blueprint, Storage $storage): ActionSet
    {
        $linkStoreBlueprint = new Blueprint();
        $idField = clone $blueprint->primaryIndex()->fields()[0];

        $idField->name = 'id';
        $idField->isGenerated = false;

        $idForeignKey = new Blueprint\ForeignKey(
            'id',
            $blueprint->name,
            $blueprint->primaryIndex()->fields()[0]->name,
            Action::Cascade,
            Action::Cascade
        );

        $valueField = new Blueprint\Field(
            name: 'entityClass',
            type: Type::Text,
            isGenerated: false,
        );

        $primaryIndex = new Blueprint\Index([$idField], true);
        $linkStoreBlueprint->name = $this->namingStrategy->determine($blueprint->name);

        $linkStoreBlueprint
            ->addField($idField)
            ->addField($valueField)
            ->addIndex($primaryIndex)
            ->addForeignKey($idForeignKey);

        return $this->migrationBuilderManager->for($storage)
            ->buildActions($storage, $linkStoreBlueprint);
    }
}
