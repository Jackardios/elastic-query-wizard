<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use BadMethodCallException;
use Illuminate\Support\Str;

trait HasParameters
{
    /** @var array<string, mixed> */
    protected array $queryParameters = [];

    /**
     * @template T of object
     * @param T $queryBuilder
     * @return T
     */
    public function applyParametersOnQuery(object $queryBuilder): object
    {
        foreach ($this->queryParameters as $name => $value) {
            $methodName = Str::camel($name);

            if (! method_exists($queryBuilder, $methodName)) {
                throw new BadMethodCallException(
                    sprintf('Method "%s" does not exist on %s.', $methodName, get_class($queryBuilder))
                );
            }

            $queryBuilder->{$methodName}($value);
        }

        return $queryBuilder;
    }

    /**
     * @return $this
     */
    public function withParameters(array $parameters): static
    {
        $this->queryParameters = array_merge($this->queryParameters, $parameters);

        return $this;
    }
}
