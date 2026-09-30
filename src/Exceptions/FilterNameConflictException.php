<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

/**
 * Thrown when a leaf is in two groups, where one request value would apply in both.
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
}
