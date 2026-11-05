# medas-migration-builder

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

Generates PHP migration class files by diffing the expected schema (derived from entity attribute definitions) against the actual current schema in each storage backend. Driver-specific packages (`medas-pdo-mysql-migration-builder`, `medas-json-storage-migration-builder`) implement the `MigrationBuilder` interface and handle their respective storage types.

**How `make-migration` works:**

1. `MakeMigrationCommand` scans all configured entity directories and directories from `PackageEntities` implementors for `#[Entity]` classes
2. `EntityBlueprintBuilder` builds a `Blueprint` from each entity's attribute metadata (fields, indices, foreign keys, collection tables, etc.)
3. `BuilderResolver` finds the correct `MigrationBuilder` implementation for the entity's storage backend
4. Each `MigrationBuilder` compares the blueprint against the current storage state and emits SQL (or file-creation) statements into the generated `migrate()` and `undo()` methods
5. `MigrationFactory` wraps the collected statements in a timestamped `Migration` class and writes it to the configured migrations directory

If no changes are detected between the blueprint and the current storage state, no migration file is created.

**`Blueprint`** is a description of an entity's complete storage structure: field names, types, nullability, defaults, indices (unique and non-unique), foreign keys, and whether the original class name should be stored (for inheritance). `ChangeFinder` diffs two `Blueprint` instances to determine which fields, indices and foreign keys a migration should add or change. It is a projection of the full store diff described below.

## Structure diff

`Structure\Diff\StructureDiffer` compares everything the backend expects to find in its storages with what those storages actually contain, and returns a `Structure\Diff\Result\StructureDiff`:

- **stores**: missing, superfluous, changed or equal, per storage
- **fields**: missing, superfluous, or changed; a change is *compatible* when the backend can live with it (a column that allows a wider range than needed, a boolean kept as an integer) and *incompatible* otherwise
- **indexes** and **foreign keys**: missing, superfluous, or changed (foreign key actions)
- **field order** (optional): the smallest set of fields that would have to move, ignoring fields only one side has

Migrations use the same comparison: `ChangeFinder` keeps only what is missing or incompatibly changed (`Structure\Diff\ChangesProjector`).

**What the backend expects** comes from all `Structure\Diff\ExpectedStructureProvider` implementations. This package provides the stores of all stored entities and the migrations and sequences stores. A package that keeps data in a store of its own should implement the interface, otherwise its store shows up as superfluous. Join tables and original class (linking) stores are derived automatically.

**Reading a storage** is driver specific: each driver package implements `Structure\Diff\StructureReader` next to its `MigrationBuilder` (`medas-pdo-mysql-migration-builder`, `medas-pdo-sqlite-migration-builder` and `medas-json-storage-migration-builder` do). Storages without a reader are listed as not compared. JSON stores only know field names and their key, so only those are compared.

Only storages the backend uses are compared, as only those are known.

## Configuration options

The migration directory and entity directories come from `medas-storage-manager` and `medas-entity-manager` respectively — see those packages for details.

| Option | Default | Description |
|---|---|---|
| `migration-builder.ignored-stores` | `[]` | Store name patterns (fnmatch, e.g. `backup_*`) the structure diff never reports as superfluous |

## Usage

### Package developer context

Register the package and at least one driver-specific migration builder:

```php
use Medas\MigrationBuilder\MigrationBuilderPackage;
use Medas\PdoMysqlMigrationBuilder\PdoMysqlMigrationBuilderPackage;

MigrationBuilderPackage::instance();
PdoMysqlMigrationBuilderPackage::instance();
```

**Generating a migration programmatically:**

```php
use Medas\MigrationBuilder\{MigrationFactory, MigrationFactory\Settings};
use Medas\Core\Attributes\Service;

#[Service]
readonly class MigrationGenerator
{
    public function __construct(
        private MigrationFactory $factory,
    ) {}

    public function generate(string $migrationsDir): string|null
    {
        $settings = new Settings(
            sourceDirectories: ['src/Entities'],
            migrationsDirectory: $migrationsDir,
        );

        // Returns the path to the created file, or null if no migration was needed
        return $this->factory->createMigration($settings);
    }
}
```

**`--clean` flag** — pass `ignoreExistingStorage: true` in `Settings` (or `--clean` on the CLI) to generate a migration that creates all stores from scratch, ignoring any existing tables or files. Useful for generating a baseline migration for a fresh deployment.

**Implementing a custom `MigrationBuilder`:**

```php
use Medas\MigrationBuilder\{MigrationBuilder, Structure\Blueprint};
use Medas\StorageManager\{Interfaces\Storage, UnitOfWork\ActionSet};
use Medas\FileBuilder\PhpClass\MethodDefinition;
use Medas\Core\Attributes\Service;

#[Service]
readonly class RedisStorageMigrationBuilder implements MigrationBuilder
{
    public function handles(Storage $storage): bool
    {
        return $storage instanceof RedisStorage;
    }

    public function build(
        Storage          $storage,
        Blueprint        $blueprint,
        MethodDefinition $migrateMethod,
        MethodDefinition $undoMethod,
        bool             $ignoreExistingStructure = false,
    ): bool
    {
        // Append PHP code strings to $migrateMethod->body and $undoMethod->body
        // Return true if any code was added, false if no migration is needed
        return false;
    }

    public function buildActions(Storage $storage, Blueprint $blueprint, bool $ignoreExistingStructure = false): ActionSet
    {
        return new ActionSet();
    }
}
```

### Backend user context

**Generating a migration from the console:**

```bash
php bin/medas migration-builder:make-migration
# Short alias:
php bin/medas c.migration

# Generate from scratch (ignore current schema):
php bin/medas c.migration --clean
```

If a migration is needed, a file like `migrations/Migration20260522120000123456.php` is created. If the schema is already up to date, a "no need to create a migration file" message is printed instead.

**Comparing the storages with the backend:**

```bash
php bin/medas storage-diff

# Also check the field order, only one storage, only some stores:
php bin/medas storage-diff --order --storage=default --store="order*"

# Hide differences the backend can live with, and fail (exit code 1) on the rest, e.g. in CI:
php bin/medas storage-diff --only-problems --fail

# Never report these stores as superfluous (on top of the configured ignored-stores):
php bin/medas storage-diff --ignore="backup_*,tmp_*"
```

**Using the diff programmatically:**

```php
use Medas\MigrationBuilder\MigrationFactory\SourceDirectoryFinder;
use Medas\MigrationBuilder\Structure\Diff\{Result\Status, Settings, StructureDiffer};

$diff = service(StructureDiffer::class)->diff(
    new Settings(service(SourceDirectoryFinder::class)->find(), checkOrder: true)
);

foreach ($diff->storesWithStatus(Status::Superfluous) as $store) {
    echo $store->storageName, '.', $store->name, PHP_EOL;
}
```

**Running migrations** — use `medas-storage-manager`'s migrate command:

```bash
php bin/medas storage-manager:migrate
```
