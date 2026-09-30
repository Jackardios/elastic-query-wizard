<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

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
            $queryBuilder->{Str::camel($name)}($value);
        }

        return $queryBuilder;
    }

    /**
     * @param  array<string, mixed>  $parameters  Query builder setter name, in snake or camel case => value
     * @return $this
     *
     * @throws InvalidArgumentException When a query the filter builds has no setter for a parameter, or its setter does
     *                                  not take the value's type
     */
    public function withParameters(array $parameters): static
    {
        foreach ($parameters as $name => $value) {
            $this->assertParameterIsSupported((string) $name, $value);
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
     * the setters every one of them has, with a value of the type they take.
     *
     * @return list<class-string>
     */
    abstract protected function parameterQueryClasses(): array;

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

    private function assertParameterIsSupported(string $name, mixed $value): void
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

            $type = (new ReflectionMethod($queryClass, $methodName))->getParameters()[0]->getType();

            if ($type !== null && ! self::valueHasType($value, $type)) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter `%s` of %s expects %s, got %s.',
                    $name,
                    static::class,
                    $type,
                    get_debug_type($value)
                ));
            }
        }
    }

    /**
     * Whether the setter takes the value as a strictly typed call would.
     */
    private static function valueHasType(mixed $value, ReflectionType $type): bool
    {
        if ($value === null && $type->allowsNull()) {
            return true;
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $matches = array_map(static fn (ReflectionType $member): bool => self::valueHasType($value, $member), $type->getTypes());

            return $type instanceof ReflectionUnionType ? in_array(true, $matches, true) : ! in_array(false, $matches, true);
        }

        if (! $type instanceof ReflectionNamedType) {
            return true;
        }

        return match ($type->getName()) {
            'mixed' => true,
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'array' => is_array($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            'object' => is_object($value),
            'null' => $value === null,
            default => $type->isBuiltin() || $value instanceof ($type->getName()),
        };
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
