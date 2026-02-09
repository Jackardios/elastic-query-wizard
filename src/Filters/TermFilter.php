<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

class TermFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'term';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $prepared = FilterValueSanitizer::toArray($value);

        if (empty($prepared)) {
            return;
        }

        $propertyName = $this->property;

        $query = count($prepared) === 1
            ? Query::term($propertyName, $prepared[0])
            : Query::terms($propertyName, $prepared);

        $query = $this->applyParametersOnQuery($query);

        $builder->filter($query);
    }
}
