<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

class RegexpFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'regexp';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (is_array($value)) {
            $value = reset($value) ?: '';
        }

        if (FilterValueSanitizer::isBlank($value)) {
            return;
        }

        $query = Query::regexp($this->property, (string) $value);
        $query = $this->applyParametersOnQuery($query);

        $builder->filter($query);
    }
}
