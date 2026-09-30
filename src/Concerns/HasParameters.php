<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use BadMethodCallException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionMethod;

/**
 * withParameters(): the options of a built-in filter's Elasticsearch query.
 *
 * @internal
 */
trait HasParameters
{
    /** @var array<string, mixed> */
    protected array $queryParameters = [];

    /**
     * Call the setter of each parameter given to withParameters() on the query.
     *
     * @template T of object
     *
     * @param  T  $queryBuilder
     * @return T
     */
    protected function applyParametersOnQuery(object $queryBuilder): object
    {
        foreach ($this->queryParameters as $name => $value) {
            $methodName = Str::camel($name);

            if (! method_exists($queryBuilder, $methodName)) {
                throw new BadMethodCallException(
                    sprintf('Call to undefined method %s::%s()', get_class($queryBuilder), $methodName)
                );
            }

            $queryBuilder->{$methodName}($value);
        }

        return $queryBuilder;
    }

    /**
     * @param  array<string, mixed>  $parameters  Query builder setter name, in snake or camel case => value
     * @return $this
     *
     * @throws InvalidArgumentException When a query the filter builds has no setter for a parameter
     */
    public function withParameters(array $parameters): static
    {
        foreach (array_keys($parameters) as $name) {
            $this->assertParameterIsSupported((string) $name);
        }

        $this->queryParameters = array_merge($this->queryParameters, $parameters);

        return $this;
    }

    /**
     * Whether withParameters() set any of the named parameters, in snake or
     * camel case.
     */
    protected function hasQueryParameter(string ...$names): bool
    {
        $setters = array_map(static fn (string $name): string => Str::camel($name), array_keys($this->queryParameters));

        foreach ($names as $name) {
            if (in_array(Str::camel($name), $setters, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The query classes the filter may build. withParameters() accepts only
     * the setters every one of them has; an empty list leaves the check to
     * applyParametersOnQuery().
     *
     * @return list<class-string>
     */
    protected function parameterQueryClasses(): array
    {
        return [];
    }

    /**
     * Setters the filter calls itself, by setter name, with where their value
     * comes from: withParameters() refuses them rather than override it.
     *
     * @return array<string, string>
     */
    protected function reservedParameters(): array
    {
        return [];
    }

    private function assertParameterIsSupported(string $name): void
    {
        $methodName = Str::camel($name);
        $source = $this->reservedParameters()[$methodName] ?? null;

        if ($source !== null) {
            throw new InvalidArgumentException(sprintf('Parameter `%s` is set by %s from %s.', $name, static::class, $source));
        }

        foreach ($this->parameterQueryClasses() as $queryClass) {
            if (! self::isQuerySetter($queryClass, $methodName)) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter `%s` is not supported by %s: %s has no %s() setter.',
                    $name,
                    static::class,
                    $queryClass,
                    $methodName
                ));
            }
        }
    }

    /**
     * @param  class-string  $queryClass
     */
    private static function isQuerySetter(string $queryClass, string $methodName): bool
    {
        if (! method_exists($queryClass, $methodName) || strcasecmp($methodName, 'toArray') === 0) {
            return false;
        }

        $method = new ReflectionMethod($queryClass, $methodName);

        return $method->isPublic() && ! $method->isStatic() && ! $method->isConstructor();
    }
}
