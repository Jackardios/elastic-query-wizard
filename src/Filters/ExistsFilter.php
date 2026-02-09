<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

class ExistsFilter extends AbstractElasticFilter
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

        $query = Query::exists($this->property);
        $query = $this->applyParametersOnQuery($query);

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $builder->filter($query);
        } else {
            $builder->mustNot($query);
        }
    }
}
