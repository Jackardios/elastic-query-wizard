<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidRangeValue extends InvalidQuery
{
    private const OPERATOR_MIGRATION = [
        'from' => 'gte',
        'to' => 'lte',
        'include_lower' => 'gte (instead of gt)',
        'include_upper' => 'lte (instead of lt)',
    ];

    public static function make(string $propertyName): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` must be valid array with valid keys: `gt`, `gte`, `lt` or `lte`"
        );
    }

    public static function legacyOperator(string $propertyName, string $operator): self
    {
        $suggestion = self::OPERATOR_MIGRATION[$operator] ?? 'gt/gte/lt/lte';

        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` uses legacy operator `$operator` which was removed in Elasticsearch 9.x. Use `$suggestion` instead."
        );
    }
}
