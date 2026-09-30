<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;

/**
 * Filter by field existence: true matches an existing field, false a missing one.
 */
final class ExistsFilter extends AbstractExistsFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [ExistsQuery::class];
    }

    protected function existsQuery(): QueryInterface
    {
        return $this->applyParametersOnQuery(parent::existsQuery());
    }

    protected function matchesMissingField(bool $value): bool
    {
        return ! $value;
    }
}
