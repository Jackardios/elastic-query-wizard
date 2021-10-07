<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;

abstract class AbstractParameterizedElasticFilter extends AbstractElasticFilter
{
    use HasParameters;

    public function __construct(string $propertyName, ?string $alias = null, array $parameters = [], $default = null)
    {
        $this->parameters = $parameters;
        parent::__construct($propertyName, $alias, $default);
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function withParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }
}
