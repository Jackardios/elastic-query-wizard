<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\LimitsValueLength;
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
    use LimitsValueLength;

    /**
     * The characters that can end a term or start a new one inside it.
     */
    private const TERM_BREAKS = " \t\n\r\v\f()!:\\\"~^";

    /**
     * The value is one text, which may contain the separator; a list is a 400.
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
     * ranges, regular expressions and escaped characters are not such terms.
     *
     * A new term starts after whitespace, `(`, `)`, `:`, `!`, a phrase, a range,
     * a regular expression, and fuzziness or a boost (`a~*b`, `a^2*b`), as
     * Lucene's query parser reads it. The scan is linear, so a long value can't
     * exhaust the PCRE limits and slip through.
     */
    private static function hasLeadingWildcard(string $query): bool
    {
        $length = strlen($query);
        $atTermStart = true;

        for ($i = 0; $i < $length; $i++) {
            if (! $atTermStart) {
                $i += strcspn($query, self::TERM_BREAKS, $i);

                if ($i >= $length) {
                    break;
                }
            }

            $char = $query[$i];

            if (ctype_space($char) || $char === '(' || $char === ')' || $char === ':' || $char === '!') {
                $atTermStart = true;
            } elseif ($char === '\\') {
                $i++;
                $atTermStart = false;
            } elseif ($char === '"') {
                $i = self::closingPosition($query, $i, '"');
                $atTermStart = true;
            } elseif ($char === '~' || $char === '^') {
                $i += strspn($query, '0123456789.', $i + 1);
                $atTermStart = true;
            } elseif ($char === '+' || $char === '-') {
                continue;
            } elseif ($char === '/') {
                $i = self::closingPosition($query, $i, '/');
            } elseif ($char === '[' || $char === '{') {
                $i = self::closingPosition($query, $i, ']}');
            } elseif ($char === '*' || $char === '?') {
                $next = $query[$i + 1] ?? ' ';

                if ($char === '?' || ! (ctype_space($next) || str_contains(')~^:', $next))) {
                    return true;
                }
            } else {
                $atTermStart = false;
            }
        }

        return false;
    }

    /**
     * The position of the unescaped character that closes the construct opened
     * at $open, or the last position when the value ends first.
     */
    private static function closingPosition(string $query, int $open, string $closers): int
    {
        $length = strlen($query);

        for ($i = $open + 1; $i < $length; $i++) {
            if ($query[$i] === '\\') {
                $i++;
            } elseif (str_contains($closers, $query[$i])) {
                return $i;
            }
        }

        return $length - 1;
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
