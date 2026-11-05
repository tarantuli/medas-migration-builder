<?php

declare(strict_types=1);

namespace Medas\MigrationBuilderTest\Functional\Structure\Diff;

use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\Structure\{Blueprint, Diff\StoreNarrower};
use Medas\StorageManager\Type;
use PHPUnit\Framework\TestCase;

class StoreNarrowerTest extends TestCase
{
    private const string PARENT_STORE = 'discount_rules';
    private const string CHILD_STORE = 'cost_price_discount_rules';

    // The child's store repeats the parent's primary key, like the create table builders do
    public function testInheritedPrimaryKeyIsKeptWithAForeignKeyToTheParent(): void
    {
        $blueprint = $this->childBlueprint();
        $store = new StoreNarrower()->narrow($blueprint);

        self::assertSame(['id', 'reference'], array_column($store->blueprint->fields, 'name'));
        self::assertFalse($store->blueprint->fieldByName('id')->isGenerated);
        self::assertCount(1, $store->blueprint->foreignKeys);
        self::assertSame(self::PARENT_STORE, $store->blueprint->foreignKeys[0]->foreignEntity);

        self::assertSame(
            [$store->blueprint->foreignKeys[0]->hash()],
            $store->addedForeignKeyHashes
        );

        // The cached blueprint itself is left alone
        self::assertTrue($blueprint->fieldByName('id')->isGenerated);
    }

    public function testParentPartsAreLeftOut(): void
    {
        $blueprint = $this->childBlueprint();

        $blueprint->addIndex(new Blueprint\Index([$blueprint->fieldByName('code')], isUnique: true));
        $blueprint->addIndex(new Blueprint\Index([$blueprint->fieldByName('reference')], isUnique: true));

        $blueprint->addForeignKey(new Blueprint\ForeignKey(
            'code',
            'codes',
            'id',
            Action::Cascade,
            Action::Cascade,
            self::PARENT_STORE
        ));

        $store = new StoreNarrower()->narrow($blueprint);

        $indexedFields = array_map(
            fn($index) => $index->fields()[0]->name,
            $store->blueprint->indexes
        );

        self::assertSame(['id', 'reference'], $indexedFields);
        self::assertCount(1, $store->blueprint->foreignKeys);
    }

    public function testCollectionFieldsAreReturnedSeparately(): void
    {
        $blueprint = $this->childBlueprint();

        $blueprint->addField(new Blueprint\Field('tags', Type::Collection, self::CHILD_STORE));
        $blueprint->addField(new Blueprint\Field('owners', Type::Collection, self::PARENT_STORE));

        $store = new StoreNarrower()->narrow($blueprint);

        self::assertNull($store->blueprint->fieldByName('tags'));
        self::assertSame(['tags'], array_column($store->collectionFields, 'name'));
    }

    private function childBlueprint(): Blueprint
    {
        $blueprint = new Blueprint();
        $id = new Blueprint\Field('id', Type::Integer, self::PARENT_STORE, isGenerated: true);
        $blueprint->name = self::CHILD_STORE;
        $blueprint->parent = self::PARENT_STORE;

        $blueprint
            ->addField($id)
            ->addField(new Blueprint\Field('code', Type::Text, self::PARENT_STORE))
            ->addField(new Blueprint\Field('reference', Type::Text, self::CHILD_STORE))
            ->addIndex(new Blueprint\Index([$id], true, true));

        return $blueprint;
    }
}
