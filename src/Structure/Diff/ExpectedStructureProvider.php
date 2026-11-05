<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

/**
 * Tells the structure differ about stores the backend wants to use. Implement this in
 * any package that keeps data in a storage, so its stores are expected instead of being
 * reported as superfluous. Implementations are found automatically.
 */
interface ExpectedStructureProvider
{
    /** @return iterable<ExpectedStore> */
    public function provide(Settings $settings): iterable;
}
