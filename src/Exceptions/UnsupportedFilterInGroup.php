<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;

/**
 * Thrown when a filter that cannot be used inside a group is added to a group.
 *
 * Non-elastic filters (CallbackFilter, PassthroughFilter) and filters with
 * root-level side effects (TrashedFilter) cannot be applied inside groups.
 */
final class UnsupportedFilterInGroup extends \InvalidArgumentException
{
    public static function forFilter(FilterInterface $filter, string $groupName): self
    {
        return new self(sprintf(
            'Filter `%s` (%s) cannot be used inside group `%s`: a group takes AbstractElasticFilter subclasses and groups only.',
            $filter->getName(),
            $filter::class,
            $groupName
        ));
    }
}
