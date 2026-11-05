<?php

declare(strict_types=1);

namespace Medas\MigrationBuilderTest\Functional\Structure\Changes;

use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\Structure\{Blueprint, Changes\ChangeFinder};
use Medas\StorageManager\Type;
use PHPUnit\Framework\TestCase;

// A child entity in joined inheritance carries its parent's fields, indexes and
// relations in its blueprint, each marked with the parent's store. Comparing the
// child's store must leave those to the parent's.
class ChangeFinderTest extends TestCase
{
    private const string PARENT_STORE = 'discount_rules';
    private const string CHILD_STORE = 'cost_price_discount_rules';

    public function testInheritedForeignKeyIsNotAddedToTheChildStore(): void
    {
        $expected = $this->childBlueprint();

        $expected->addForeignKey($this->foreignKey('category', 'product_categories', self::PARENT_STORE));
        $expected->addForeignKey($this->foreignKey('supplier', 'suppliers', self::CHILD_STORE));

        $changes = new ChangeFinder()->find($expected, $this->childBlueprint());

        self::assertNotNull($changes);
        self::assertSame(['supplier'], array_column($changes->addForeignKey, 'field'));
    }

    public function testIndexOverAnInheritedFieldIsNotAddedToTheChildStore(): void
    {
        $inherited = new Blueprint\Field('code', Type::Text, self::PARENT_STORE);
        $own = new Blueprint\Field('reference', Type::Text, self::CHILD_STORE);
        $expected = $this->childBlueprint();

        $expected->addField($inherited)->addField($own);
        $expected->addIndex(new Blueprint\Index([$inherited], false, true));
        $expected->addIndex(new Blueprint\Index([$own], false, true));

        $existing = $this->childBlueprint();

        $existing->addField(new Blueprint\Field('reference', Type::Text));

        $changes = new ChangeFinder()->find($expected, $existing);

        self::assertNotNull($changes);
        self::assertCount(1, $changes->addIndexes);
        self::assertSame('reference', $changes->addIndexes[0]->fields()[0]->name);
    }

    // With everything the child owns already in place, inherited parts alone are no
    // reason for a migration.
    public function testOnlyInheritedPartsMeanNoChanges(): void
    {
        $expected = $this->childBlueprint();

        $expected->addForeignKey($this->foreignKey('category', 'product_categories', self::PARENT_STORE));

        self::assertNull(new ChangeFinder()->find($expected, $this->childBlueprint()));
    }

    private function foreignKey(string $field, string $foreignEntity, string $store): Blueprint\ForeignKey
    {
        return new Blueprint\ForeignKey(
            $field,
            $foreignEntity,
            'id',
            Action::Restrict,
            Action::Cascade,
            $store
        );
    }

    private function childBlueprint(): Blueprint
    {
        $blueprint = new Blueprint();

        $blueprint->name = self::CHILD_STORE;
        $blueprint->parent = self::PARENT_STORE;

        return $blueprint;
    }
}
