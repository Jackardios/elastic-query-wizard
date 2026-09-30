<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
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
final class QueryStringFilter extends AbstractTextFilter
{
    /**
     * The characters Lucene's query parser reads as neither part of a term nor
     * a wildcard: its whitespace (space, tab, CR, LF, and U+3000, which the
     * scan replaces by a space) and its syntax characters, except the escape.
     */
    private const SEPARATORS = " \t\n\r()!:^~\"[]{}/";

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [QueryStringQuery::class];
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        if (! $this->allowsLeadingWildcard() && self::hasLeadingWildcard($text)) {
            throw InvalidFilterValue::make($text, $this, 'A term may not start with `*` or `?`.');
        }

        $query = Query::queryString($text)->allowLeadingWildcard(false);

        if (! $this->hasQueryParameter('fields', 'default_field')) {
            $query->fields([$this->property]);
        }

        return $query;
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
     * refuses without `allow_leading_wildcard`. A lone `*` (one followed by a
     * separator or nothing), quoted phrases, ranges, regular expressions and
     * escaped characters are not such terms.
     *
     * A new term starts after whitespace, `(`, `)`, `:`, `!`, a phrase, a
     * range, a regular expression, and fuzziness or a boost (`a~*b`, `a^2*b`),
     * as Lucene's query parser reads it. The scan is linear, so a long value
     * can't exhaust the PCRE limits and slip through.
     */
    private static function hasLeadingWildcard(string $query): bool
    {
        $query = str_replace("\u{3000}", ' ', $query);
        $length = strlen($query);
        $atTermStart = true;

        for ($i = 0; $i < $length; $i++) {
            if (! $atTermStart) {
                $i += strcspn($query, self::SEPARATORS.'\\', $i);

                if ($i >= $length) {
                    break;
                }
            }

            $char = $query[$i];

            if ($char === '\\') {
                $i++;
                $atTermStart = false;
            } elseif ($char === '"') {
                $i = self::closingPosition($query, $i, '"');
                $atTermStart = true;
            } elseif ($char === '/') {
                $i = self::closingPosition($query, $i, '/');
                $atTermStart = true;
            } elseif ($char === '[' || $char === '{') {
                $i = self::closingPosition($query, $i, ']}');
                $atTermStart = true;
            } elseif ($char === '~') {
                $i = self::fuzzinessEnd($query, $i + 1) - 1;
                $atTermStart = true;
            } elseif ($char === '^') {
                $i += strspn($query, '0123456789.', $i + 1);
                $atTermStart = true;
            } elseif (str_contains(self::SEPARATORS, $char)) {
                $atTermStart = true;
            } elseif ($char === '+' || $char === '-') {
                continue;
            } elseif ($char === '?' || $char === '*') {
                if ($char === '?' || ($i + 1 < $length && ! str_contains(self::SEPARATORS, $query[$i + 1]))) {
                    return true;
                }
            } else {
                $atTermStart = false;
            }
        }

        return false;
    }

    /**
     * The position after the fuzziness that starts at $start: the term
     * characters that follow `~`, such as `2` or `0.5`.
     */
    private static function fuzzinessEnd(string $query, int $start): int
    {
        $length = strlen($query);
        $i = $start;

        while ($i < $length) {
            $i += strcspn($query, self::SEPARATORS.'*?\\', $i);

            if ($i >= $length || $query[$i] !== '\\') {
                break;
            }

            $i += 2;
        }

        return min($i, $length);
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
}
