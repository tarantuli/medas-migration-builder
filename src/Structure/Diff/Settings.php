<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff;

class Settings
{
    /**
     * @param string[] $sourceDirectories Where to look for entity classes
     * @param bool     $checkOrder        Whether to compare the order of the fields
     * @param string[] $ignoredStores     Patterns (fnmatch) of store names never reported as superfluous,
     *                                    on top of the configured ones
     */
    public function __construct(
        public array       $sourceDirectories = [],
        public bool        $checkOrder = false,
        public string|null $storage = null,
        public string|null $store = null,
        public array       $ignoredStores = [],
    )
    {
    }
}
