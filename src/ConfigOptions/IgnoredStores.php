<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\ConfigOptions;

use Medas\Core\{
    Attributes\Service,
    Interfaces\ConfigGroup,
    Interfaces\ConfigOption,
    Interfaces\Validator
};

#[Service]
readonly class IgnoredStores implements ConfigOption, Validator
{
    public function __construct(
        private MigrationBuilderConfigGroup $group,
    )
    {
    }

    public function group(): ConfigGroup
    {
        return $this->group;
    }

    public function name(): string
    {
        return 'ignored-stores';
    }

    public function description(): string
    {
        return 'Store name patterns (fnmatch, e.g. "backup_*") the structure diff never reports as superfluous';
    }

    public function hasDefault(): bool
    {
        return true;
    }

    public function default(): array
    {
        return [];
    }

    public function isValid(mixed $value): bool
    {
        return is_array($value) && array_all($value, fn(mixed $pattern) => is_string($pattern));
    }
}
