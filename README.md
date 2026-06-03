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

**`Blueprint`** is a description of an entity's complete storage structure: field names, types, nullability, defaults, indices (unique and non-unique), foreign keys, and whether the original class name should be stored (for inheritance). `ChangeFinder` diffs two `Blueprint` instances to determine which fields and indices were added, removed, or modified.

## Configuration options

The migration directory and entity directories come from `medas-storage-manager` and `medas-entity-manager` respectively — see those packages for details.

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

**Running migrations** — use `medas-storage-manager`'s migrate command:

```bash
php bin/medas storage-manager:migrate
```
