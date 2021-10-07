<?php

namespace Jackardios\ElasticQueryWizard\Concerns;

use ElasticScoutDriverPlus\Builders\AbstractParameterizedQueryBuilder;
use Illuminate\Support\Str;

trait HasParameters
{
    protected array $parameters;

    public function applyParametersOnQuery(AbstractParameterizedQueryBuilder $queryBuilder): AbstractParameterizedQueryBuilder
    {
        foreach ($this->parameters as $name => $value) {
            $methodName = Str::camel($name);
            $queryBuilder->{$methodName}($methodName);
        }

        return $queryBuilder;
    }
}
