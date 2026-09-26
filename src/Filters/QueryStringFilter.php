<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\FullText\QueryStringQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * Elasticsearch query-string syntax, for trusted input only: a field-qualified
 * term (`other_field:value`) reads any field of the document.
 *
 * Searches the property unless withParameters() sets `fields` or
 * `default_field`, and refuses a term that starts with a wildcard unless it
 * sets `allow_leading_wildcard`.
 */
final class QueryStringFilter extends AbstractElasticFilter
{
    use HasParameters;

    /**
     * The value is one text, which may contain the separator; call
     * withValueSplitting() to accept a list.
     */
    protected bool $splitValues = false;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'query_string';
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [QueryStringQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (is_array($value)) {
            $value = FilterValueSanitizer::arrayToCommaSeparatedString($value);
        }

        $prepared = FilterValueSanitizer::toString($value);

        if ($prepared === null || $prepared === '') {
            return null;
        }

        if (! $this->allowsLeadingWildcard() && self::hasLeadingWildcard($prepared)) {
            throw InvalidFilterValue::make($value, $this, 'A term may not start with `*` or `?`.');
        }

        $query = Query::queryString($prepared)->allowLeadingWildcard(false);

        if (! $this->hasQueryParameter('fields', 'default_field')) {
            $query->fields([$this->property]);
        }

        return $this->applyParametersOnQuery($query);
    }

    private function allowsLeadingWildcard(): bool
    {
        foreach ($this->queryParameters as $name => $value) {
            if (Str::camel($name) === 'allowLeadingWildcard') {
                return $value === true;
            }
        }

        return false;
    }

    /**
     * Whether a term of the query starts with `*` or `?`, which Elasticsearch
     * refuses without `allow_leading_wildcard`. A lone `*`, quoted phrases,
     * ranges and escaped characters are not such terms.
     */
    private static function hasLeadingWildcard(string $query): bool
    {
        $unquoted = (string) preg_replace(['/"(?:\\\\.|[^"\\\\])*"?/s', '/[\[{][^\]}]*[\]}]?/'], ' ', $query);

        preg_match_all('/(?:^|[\s():])[+\-!]*((?:\\\\.|[^\s():\\\\])+)/', $unquoted, $matches);

        foreach ($matches[1] as $term) {
            if ($term !== '*' && ($term[0] === '*' || $term[0] === '?')) {
                return true;
            }
        }

        return false;
    }
}
