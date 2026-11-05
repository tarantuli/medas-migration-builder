<?php

declare(strict_types=1);

namespace Medas\MigrationBuilder\Structure\Diff\Result;

enum Severity: int
{
    public static function worst(self ...$severities): self
    {
        $worst = self::Equal;

        foreach ($severities as $severity) {
            if ($severity->isWorseThan($worst)) {
                $worst = $severity;
            }
        }

        return $worst;
    }

    // No difference at all
    case Equal = 0;

    // A difference the backend can live with, e.g. a column that allows a wider range
    // than the entity needs. Migrations leave these alone.
    case Compatible = 1;

    // A difference a migration should resolve
    case Incompatible = 2;

    public function isWorseThan(self $other): bool
    {
        return $this->value > $other->value;
    }
}
