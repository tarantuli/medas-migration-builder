<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

enum Status: string
{
    // Present on both sides and the same
    case Equal = 'equal';

    // Expected by the backend, but not present in the storage
    case Missing = 'missing';

    // Present in the storage, but not expected by the backend
    case Superfluous = 'superfluous';

    // Present on both sides, but different
    case Changed = 'changed';
}
