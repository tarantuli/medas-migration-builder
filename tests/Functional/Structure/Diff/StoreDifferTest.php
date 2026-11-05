<?php

declare(strict_types=1);

namespace Medas\MigrationBuilderTest\Functional\Structure\Diff;

use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\MigrationBuilder\Structure\Diff\{Result\Severity, Result\Status, StoreDiffer};
use Medas\StorageManager\Type;
use PHPUnit\Framework\TestCase;

class StoreDifferTest extends TestCase
{
    public function testEqualStores(): void
    {
        $diff = new StoreDiffer()->diff(
            'default',
            $this->blueprint(['id', 'name']),
            $this->blueprint(['id', 'name'])
        );

        self::assertSame(Status::Equal, $diff->status);
        self::assertSame(Severity::Equal, $diff->severity());
    }

    public function testMissingAndSuperfluousFields(): void
    {
        $diff = new StoreDiffer()->diff(
            'default',
            $this->blueprint(['id', 'name', 'email']),
            $this->blueprint(['id', 'name', 'legacy'])
        );

        self::assertSame(Status::Changed, $diff->status);
        self::assertSame(['email'], array_column($diff->fieldsWithStatus(Status::Missing), 'name'));

        self::assertSame(
            ['legacy'],
            array_column($diff->fieldsWithStatus(Status::Superfluous), 'name')
        );

        self::assertTrue($diff->hasIncompatibleDifferences());
    }

    public function testMissingStore(): void
    {
        $diff = new StoreDiffer()->diff('default', $this->blueprint(['id', 'name']), null);

        self::assertSame(Status::Missing, $diff->status);
        self::assertSame('items', $diff->name);
        self::assertCount(2, $diff->fieldsWithStatus(Status::Missing));
    }

    public function testSuperfluousStore(): void
    {
        $diff = new StoreDiffer()->diff('default', null, $this->blueprint(['id']));

        self::assertSame(Status::Superfluous, $diff->status);
        self::assertSame(Severity::Incompatible, $diff->severity());
    }

    public function testWiderColumnIsCompatible(): void
    {
        $expected = $this->blueprint([]);
        $existing = $this->blueprint([]);

        $expected->addField(new Blueprint\Field('code', Type::Text, maxLength: 100));

        $existing->addField(new Blueprint\Field('code', Type::Text, maxLength: 255));

        $field = new StoreDiffer()->diff('default', $expected, $existing)->fields[0];

        self::assertSame(Status::Changed, $field->status);
        self::assertSame(Severity::Compatible, $field->severity);
        self::assertSame(['maxLength'], $field->compatibleProperties);
    }

    public function testNarrowerColumnIsIncompatible(): void
    {
        $expected = $this->blueprint([]);
        $existing = $this->blueprint([]);

        $expected->addField(new Blueprint\Field('code', Type::Text, maxLength: 255));

        $existing->addField(new Blueprint\Field('code', Type::Text, maxLength: 100));

        $field = new StoreDiffer()->diff('default', $expected, $existing)->fields[0];

        self::assertSame(Severity::Incompatible, $field->severity);
        self::assertSame(['maxLength'], $field->incompatibleProperties);
    }

    public function testBooleanStoredAsIntegerIsCompatible(): void
    {
        $expected = $this->blueprint([]);
        $existing = $this->blueprint([]);

        $expected->addField(new Blueprint\Field('active', Type::Boolean, maxValue: 1));

        $existing->addField(new Blueprint\Field('active', Type::Integer, maxValue: 1));

        self::assertSame(
            Severity::Compatible,
            new StoreDiffer()->diff('default', $expected, $existing)->severity()
        );
    }

    public function testNullDefaultEqualsNoDefault(): void
    {
        $expected = $this->blueprint([]);
        $existing = $this->blueprint([]);

        $expected->addField(new Blueprint\Field('note', Type::Text, isNullable: true, hasDefault: true));

        $existing->addField(new Blueprint\Field('note', Type::Text, isNullable: true));

        self::assertSame(
            Status::Equal,
            new StoreDiffer()->diff('default', $expected, $existing)->status
        );
    }

    public function testIndexes(): void
    {
        $expected = $this->blueprint(['id', 'email', 'code']);
        $existing = $this->blueprint(['id', 'email', 'code']);

        $expected->addIndex(new Blueprint\Index([$expected->fieldByName('email')], isUnique: true));

        $existing->addIndex(new Blueprint\Index([$existing->fieldByName('code')]));

        $diff = new StoreDiffer()->diff('default', $expected, $existing);

        self::assertSame(
            [['email']],
            array_map(fn($index) => $index->fieldNames(), $diff->indexesWithStatus(Status::Missing))
        );

        self::assertSame(
            [['code']],
            array_map(fn($index) => $index->fieldNames(), $diff->indexesWithStatus(Status::Superfluous))
        );
    }

    // MySQL adds an index for each foreign key without one; that isn't superfluous
    public function testIndexImpliedByForeignKeyIsNotSuperfluous(): void
    {
        $expected = $this->blueprint(['id', 'country']);
        $existing = $this->blueprint(['id', 'country']);

        $foreignKey = new Blueprint\ForeignKey(
            'country',
            'countries',
            'id',
            Action::Restrict,
            Action::Cascade
        );

        $expected->addForeignKey($foreignKey);

        $existing->addForeignKey(clone $foreignKey);
        $existing->addIndex(new Blueprint\Index([$existing->fieldByName('country')]));

        self::assertSame(
            Status::Equal,
            new StoreDiffer()->diff('default', $expected, $existing)->status
        );
    }

    public function testForeignKeys(): void
    {
        $expected = $this->blueprint(['id', 'country', 'owner']);
        $existing = $this->blueprint(['id', 'country', 'owner', 'group']);

        $expected->addForeignKey(new Blueprint\ForeignKey(
            'country',
            'countries',
            'id',
            Action::Cascade,
            Action::Cascade
        ));

        $expected->addForeignKey(new Blueprint\ForeignKey('owner', 'users', 'id', Action::Cascade, Action::Cascade));

        $existing->addForeignKey(new Blueprint\ForeignKey(
            'country',
            'countries',
            'id',
            Action::Restrict,
            Action::Cascade
        ));

        $existing->addForeignKey(new Blueprint\ForeignKey('group', 'groups', 'id', Action::Cascade, Action::Cascade));

        $diff = new StoreDiffer()->diff('default', $expected, $existing);
        $changed = $diff->foreignKeysWithStatus(Status::Changed);

        self::assertCount(1, $changed);
        self::assertSame(['onDelete'], $changed[0]->differentProperties);

        self::assertSame(
            'owner',
            $diff->foreignKeysWithStatus(Status::Missing)[0]->foreignKey()->field
        );

        self::assertSame(
            'group',
            $diff->foreignKeysWithStatus(Status::Superfluous)[0]->foreignKey()->field
        );
    }

    public function testOrderIsOnlyCheckedOnRequest(): void
    {
        $diff = new StoreDiffer()->diff(
            'default',
            $this->blueprint(['id', 'a', 'b']),
            $this->blueprint(['id', 'b', 'a'])
        );

        self::assertNull($diff->order);
        self::assertSame(Status::Equal, $diff->status);
    }

    public function testMovedFieldsAreTheSmallestSet(): void
    {
        $diff = new StoreDiffer()->diff(
            'default',
            $this->blueprint(['id', 'a', 'b', 'c', 'd']),
            $this->blueprint(['id', 'b', 'c', 'd', 'a']),
            checkOrder: true
        );

        self::assertSame(['a'], $diff->order->movedFields);
        self::assertSame(Status::Changed, $diff->status);
        self::assertSame(Severity::Compatible, $diff->severity());
    }

    // A field one side lacks doesn't make the fields after it out of order
    public function testMissingFieldDoesNotDisturbTheOrder(): void
    {
        $diff = new StoreDiffer()->diff(
            'default',
            $this->blueprint(['id', 'new', 'a', 'b']),
            $this->blueprint(['id', 'a', 'b', 'old']),
            checkOrder: true
        );

        self::assertSame(['id', 'a', 'b'], $diff->order->expectedOrder);
        self::assertTrue($diff->order->isEqual());
    }

    /** @param string[] $fieldNames */
    private function blueprint(array $fieldNames): Blueprint
    {
        $blueprint = new Blueprint();

        $blueprint->name = 'items';

        foreach ($fieldNames as $fieldName) {
            $blueprint->addField(new Blueprint\Field($fieldName, Type::Text));
        }

        return $blueprint;
    }
}
