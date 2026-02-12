<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use DateTimeInterface;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Specialized range filter for date fields with custom from/to keys.
 *
 * Unlike RangeFilter, this uses configurable keys (default: 'from'/'to')
 * and internally converts them to ES 9.x compatible gte/lte operators.
 *
 * @example filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31
 */
class DateRangeFilter extends AbstractElasticFilter
{
    use HasParameters;

    protected string $fromKey = 'from';
    protected string $toKey = 'to';
    protected ?string $dateFormat = null;
    protected ?string $timezone = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
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
     * Set the date format for Elasticsearch.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-date-format.html
     */
    public function dateFormat(string $format): static
    {
        $this->dateFormat = $format;
        return $this;
    }

    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getType(): string
    {
        return 'date_range';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (!is_array($value)) {
            return;
        }

        $from = $this->normalizeDate($value[$this->fromKey] ?? null);
        $to = $this->normalizeDate($value[$this->toKey] ?? null);

        if ($from === null && $to === null) {
            return;
        }

        $query = Query::range($this->property);

        if ($from !== null) {
            $query->gte($from);
        }
        if ($to !== null) {
            $query->lte($to);
        }
        if ($this->dateFormat !== null) {
            $query->format($this->dateFormat);
        }
        if ($this->timezone !== null) {
            $query->timeZone($this->timezone);
        }

        $query = $this->applyParametersOnQuery($query);
        $builder->filter($query);
    }

    protected function normalizeDate(mixed $value): string|int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s');
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        return null;
    }
}
