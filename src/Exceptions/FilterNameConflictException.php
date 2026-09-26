<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

/**
 * Thrown when the allowed filters use a name twice in a way that would drop or
 * double a filter: a group named like another allowed filter replaces it, and a
 * leaf in two groups applies in both.
 */
class FilterNameConflictException extends \InvalidArgumentException
{
    public static function groupNameTaken(string $groupName): self
    {
        return new self(
            "Group '{$groupName}' has the name of another allowed filter, which it would replace. "
            .'Give the group a name no other allowed filter or group uses.'
        );
    }

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
}
