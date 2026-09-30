<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\LimitsValueLength;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\RegexpQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class RegexpFilter extends AbstractElasticFilter
{
    use HasParameters;
    use LimitsValueLength;

    /**
     * The value is one pattern, which may contain the separator; a list is a 400.
     */
    protected bool $splitValues = false;

    /**
     * Elasticsearch's default `index.max_regex_length`: a longer pattern fails
     * the search, so it is refused with a 400 first. maxLength() changes it
     * for an index with another limit.
     */
    private const ES_MAX_REGEX_LENGTH = 1000;

    protected function __construct(string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->maxLength = self::ES_MAX_REGEX_LENGTH;
    }

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'regexp';
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [RegexpQuery::class];
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

        $query = Query::regexp($this->property, $prepared);

        return $this->applyParametersOnQuery($query);
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
