<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

final class ExistsFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'exists';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            return;
        }

        $query = Query::exists($this->property);
        $query = $this->applyParametersOnQuery($query);

        if ($normalized) {
            $builder->filter($query);
        } else {
            $builder->mustNot($query);
        }
    }
}
