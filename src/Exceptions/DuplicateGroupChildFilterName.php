<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

/**
 * Thrown when a group tree has two leaves of one name.
 *
 * Group values are resolved by leaf filter names only, therefore duplicate
 * leaf names would make value routing ambiguous.
 */
final class DuplicateGroupChildFilterName extends \InvalidArgumentException
{
    /**
     * @param  array<int, string>  $duplicates
     */
    public static function forGroup(string $groupName, array $duplicates): self
    {
        $names = '`'.implode('`, `', $duplicates).'`';

        return new self(
            "Group `{$groupName}` has more than one leaf named {$names}. Each leaf of a group tree needs its own name."
        );
    }
}
