<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Changes;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\{Blueprint, Diff};

/**
 * Finds the changes a migration should make to bring an existing store in line with
 * the expected blueprint. This is a projection of the full store diff: see
 * {@see Diff\StoreDiffer} for the comparison and {@see Diff\ChangesProjector} for what
 * is kept of it.
 */
#[Service]
readonly class ChangeFinder
{
    public function __construct(
        private Diff\ChangesProjector $changesProjector = new Diff\ChangesProjector(),
        private Diff\StoreDiffer      $storeDiffer = new Diff\StoreDiffer(),
        private Diff\StoreNarrower    $storeNarrower = new Diff\StoreNarrower(),
    )
    {
    }

    public function find(Blueprint $expected, Blueprint $existing): Changes|null
    {
        $store = $this->storeNarrower->narrow($expected);
        $diff = $this->storeDiffer->diff('', $store->blueprint, $existing);

        return $this->changesProjector->project($diff, $store);
    }
}
