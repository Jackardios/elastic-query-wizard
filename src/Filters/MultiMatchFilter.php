<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\LimitsValueLength;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\FullText\MultiMatchQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class MultiMatchFilter extends AbstractElasticFilter
{
    use HasParameters;
    use LimitsValueLength;

    /**
     * The value is one text, which may contain the separator; a list is a 400.
     */
    protected bool $splitValues = false;

    /** @var string[] */
    protected array $fields;

    /**
     * @param  string[]  $fields
     */
    protected function __construct(array $fields, string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->fields = $fields;
    }

    /**
     * @param  string[]  $fields  The Elasticsearch fields to search across
     */
    public static function make(array $fields, string $property, ?string $alias = null): static
    {
        return new self($fields, $property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [MultiMatchQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $prepared = FilterValueSanitizer::toString($value);

        if ($prepared === null || $prepared === '') {
            return null;
        }

        $this->assertValueLength($prepared);

        $query = Query::multiMatch($this->fields, $prepared);

        return $this->applyParametersOnQuery($query);
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
