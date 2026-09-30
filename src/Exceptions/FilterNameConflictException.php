<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

/**
 * Thrown when a leaf is in two groups, where one request value would apply in both,
 * or when a leaf shadows a root filter whose default would be dropped.
 */
class FilterNameConflictException extends \InvalidArgumentException
{
    /**
     * @param  array<int, string>  $leafNames
     */
    public static function leavesInSeveralGroups(array $leafNames): self
    {
        $names = implode(', ', $leafNames);

        return new self(
            "Filter(s) {$names} are in more than one group, so one request value would apply in each of them. "
            .'Keep each filter in one group, or give the copies different aliases.'
        );
    }

    public static function shadowedDefault(string $name): self
    {
        return new self(
            "Filter `{$name}` has a default, but a group leaf of the same name reads its request key, so the default "
            .'would never apply. Set the default on the leaf.'
        );
    }
}
