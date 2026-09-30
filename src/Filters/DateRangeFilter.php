<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Range filter for date fields, reading dates like laravel-query-wizard's
 * date range filter.
 *
 * Each bound is a date (Y-m-d) or an ISO 8601 date-time, read in the filter's
 * timezone (the application's by default); a date-time with an offset keeps
 * its instant. A date names the whole day, so `to=2024-01-31` ends before
 * 2024-02-01 starts. Any other value is a 400 (InvalidFilterValue).
 *
 * The bounds reach Elasticsearch as ISO 8601 date-times with an offset, read
 * with the `strict_date_optional_time` format whatever the field's own format;
 * esFormat() puts another format first.
 *
 * @example filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31
 */
final class DateRangeFilter extends AbstractElasticFilter
{
    use HasParameters;

    private const DEFAULT_ES_FORMAT = 'strict_date_optional_time';

    protected string $fromKey = 'from';

    protected string $toKey = 'to';

    protected ?string $esFormat = null;

    protected ?DateTimeZone $timezone = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function fromKey(string $key): static
    {
        $this->fromKey = $key;

        return $this;
    }

    public function toKey(string $key): static
    {
        $this->toKey = $key;

        return $this;
    }

    /**
     * A format Elasticsearch tries on the bounds before `strict_date_optional_time`,
     * which always follows it (`yyyy-MM-dd||strict_date_optional_time`): the
     * bounds are ISO 8601 date-times with an offset, which the given format may
     * not read.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-date-format.html
     */
    public function esFormat(string $format): static
    {
        $this->esFormat = $format;

        return $this;
    }

    /**
     * @deprecated Use esFormat(). It sets the Elasticsearch format, not the format of the request values.
     */
    public function dateFormat(string $format): static
    {
        return $this->esFormat($format);
    }

    private function resolveEsFormat(): string
    {
        if ($this->esFormat === null || in_array(self::DEFAULT_ES_FORMAT, explode('||', $this->esFormat), true)) {
            return $this->esFormat ?? self::DEFAULT_ES_FORMAT;
        }

        return $this->esFormat.'||'.self::DEFAULT_ES_FORMAT;
    }

    /**
     * The timezone a bound without an offset is read in, such as
     * `Europe/Moscow` or `+03:00`; the application's by default.
     *
     * @throws InvalidArgumentException For an unknown timezone
     */
    public function timezone(string $timezone): static
    {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Unknown timezone `{$timezone}`.", 0, $exception);
        }

        return $this;
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [RangeQuery::class];
    }

    /**
     * The value must carry at least one of the configured bounds, otherwise the
     * filter would silently do nothing.
     */
    public function validateValueShape(mixed $value): ?string
    {
        if ($this->isBlankValueShape($value)) {
            return null;
        }

        if (! is_array($value)) {
            return "Filter `{$this->getName()}` expects an object with `{$this->fromKey}` and/or `{$this->toKey}` keys.";
        }

        $hasFrom = array_key_exists($this->fromKey, $value);
        $hasTo = array_key_exists($this->toKey, $value);

        if (! $hasFrom && ! $hasTo) {
            return "Filter `{$this->getName()}` expects at least one of the `{$this->fromKey}`, `{$this->toKey}` keys.";
        }

        foreach ([$this->fromKey, $this->toKey] as $key) {
            $bound = $value[$key] ?? null;

            if ($bound !== null && ! is_scalar($bound) && ! $bound instanceof DateTimeInterface) {
                return "Filter `{$this->getName()}` expects a scalar value for `{$key}`.";
            }
        }

        return null;
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (! is_array($value)) {
            return null;
        }

        $timezone = $this->timezone ?? FilterValueParser::defaultTimezone();
        $from = FilterValueParser::isoDate($value[$this->fromKey] ?? null, $this, $timezone, $this->fromKey);
        $to = FilterValueParser::isoDate($value[$this->toKey] ?? null, $this, $timezone, $this->toKey);

        if ($from === null && $to === null) {
            return null;
        }

        $query = Query::range($this->property)->format($this->resolveEsFormat());

        if ($from !== null) {
            $query->gte(self::isoDateTime($from->value));
        }

        if ($to !== null) {
            [$operator, $end] = $to->upToBound();

            if ($operator === '<') {
                $query->lt(self::isoDateTime($end->value));
            } else {
                $query->lte(self::isoDateTime($end->value));
            }
        }

        return $this->applyParametersOnQuery($query);
    }

    private static function isoDateTime(DateTimeImmutable $date): string
    {
        return $date->format($date->format('u') === '000000' ? DATE_ATOM : 'Y-m-d\TH:i:s.uP');
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
