<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * The 400 a range filter throws for a value it cannot read.
 */
final class InvalidRangeValue extends InvalidFilterValue
{
    private const OPERATOR_MIGRATION = [
        'from' => 'gte',
        'to' => 'lte',
        'include_lower' => 'gte (instead of gt)',
        'include_upper' => 'lte (instead of lt)',
    ];

    public static function invalidBounds(mixed $value, string|FilterInterface $filter): self
    {
        return self::make($value, $filter, 'Expected an array with `gt`, `gte`, `lt` or `lte` keys.');
    }

    public static function legacyOperator(mixed $value, string|FilterInterface $filter, string $operator): self
    {
        $suggestion = self::OPERATOR_MIGRATION[$operator] ?? 'gt/gte/lt/lte';

        return self::make(
            $value,
            $filter,
            "The `{$operator}` operator was removed in Elasticsearch 9.x. Use `{$suggestion}` instead."
        );
    }
}
