<?php

namespace Jackardios\ElasticQueryWizard\Concerns;

use ElasticScoutDriverPlus\Builders\AbstractParameterizedQueryBuilder;
use Illuminate\Support\Str;

trait HasParameters
{
    protected array $parameters = [];

    public function applyParametersOnQuery(AbstractParameterizedQueryBuilder $queryBuilder): AbstractParameterizedQueryBuilder
    {
        foreach ($this->parameters as $name => $value) {
            $methodName = Str::camel($name);
            $queryBuilder->{$methodName}($value);
        }

        return $queryBuilder;
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
