<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\LimitsValueLength;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\WildcardQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class WildcardFilter extends AbstractElasticFilter
{
    use HasParameters;
    use LimitsValueLength;

    /**
     * The value is one pattern, which may contain the separator; a list is a 400.
     */
    protected function __construct(string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->withoutValueSplitting();
    }

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [WildcardQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $prepared = FilterValueSanitizer::toString($value);

        if ($prepared === null || $prepared === '') {
            return null;
        }

        $this->assertValueLength($prepared);

        $query = Query::wildcard($this->property, $prepared);

        return $this->applyParametersOnQuery($query);
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
