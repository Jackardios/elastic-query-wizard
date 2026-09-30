<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticAggregation;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The proxies declare the factories they forward in @method tags, which must follow the factories of es-scout-driver.
 */
#[Group('unit')]
#[Group('factory')]
class DslProxyMethodTagsTest extends TestCase
{
    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function proxies(): array
    {
        return [
            'ElasticQuery' => [ElasticQuery::class, Query::class],
            'ElasticAggregation' => [ElasticAggregation::class, Agg::class],
        ];
    }

    /**
     * @param  class-string  $proxy
     * @param  class-string  $factory
     */
    #[Test]
    #[DataProvider('proxies')]
    public function the_method_tags_list_every_factory_with_its_return_type_and_parameters(string $proxy, string $factory): void
    {
        $this->assertSame($this->factories($factory), $this->methodTags($proxy));
    }

    /**
     * @param  class-string  $class
     * @return array<string, array{string, list<string>}>
     */
    private function factories(string $class): array
    {
        $factories = [];

        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            $returnType = $method->getReturnType();

            if (! $method->isPublic() || ! $method->isStatic() || ! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            $factories[$method->getName()] = [
                $returnType->getName(),
                array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters()),
            ];
        }

        ksort($factories);

        return $factories;
    }

    /**
     * @param  class-string  $class
     * @return array<string, array{string, list<string>}>
     */
    private function methodTags(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $source = (string) file_get_contents((string) $reflection->getFileName());
        preg_match_all('/^use ([\w\\\\]+);$/m', $source, $uses);
        $imports = [];

        foreach ($uses[1] as $use) {
            $imports[substr((string) strrchr('\\'.$use, '\\'), 1)] = $use;
        }

        preg_match_all('/@method static (\S+) (\w+)\(([^)]*)\)/', (string) $reflection->getDocComment(), $tags, PREG_SET_ORDER);
        $methods = [];

        foreach ($tags as [, $returnType, $name, $parameters]) {
            preg_match_all('/\$(\w+)/', $parameters, $parameterNames);
            $methods[$name] = [$imports[$returnType] ?? $returnType, $parameterNames[1]];
        }

        ksort($methods);

        return $methods;
    }
}
