<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class MultiMatchFilter extends AbstractElasticFilter
{
    use HasParameters;

    /** @var string[] */
    protected array $fields;

    protected function __construct(array $fields, string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->fields = $fields;
    }

    /**
     * @param string[] $fields The Elasticsearch fields to search across
     */
    public static function make(array $fields, string $property, ?string $alias = null): static
    {
        return new static($fields, $property, $alias);
    }

    public function getType(): string
    {
        return 'multi_match';
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

        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        $query = Query::multiMatch($this->fields, $value);

        return $this->applyParametersOnQuery($query);
    }
}
