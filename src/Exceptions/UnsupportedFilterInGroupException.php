<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;

/**
 * Thrown when a filter that cannot be used inside a group is added to a group.
 *
 * Filters like CallbackFilter and PassthroughFilter have no buildQuery() method
 * and cannot be applied inside a group context.
 */
class UnsupportedFilterInGroupException extends \RuntimeException
{
    public function __construct(FilterInterface $filter, string $groupName)
    {
        $filterClass = $filter::class;
        $filterName = $filter->getName();

        parent::__construct(
            "Filter '{$filterName}' ({$filterClass}) cannot be used inside group '{$groupName}'. "
            . 'Only AbstractElasticFilter subclasses and GroupInterface implementations are supported in groups.'
        );
    }

    public static function forFilter(FilterInterface $filter, string $groupName): self
    {
        return new self($filter, $groupName);
    }
}
