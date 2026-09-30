<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * The wizard declares the SearchBuilder methods it forwards in @method tags, which must follow SearchBuilder.
 */
#[Group('unit')]
class WizardMethodTagsTest extends TestCase
{
    #[Test]
    public function the_method_tags_list_every_forwarded_search_builder_method_with_its_parameters(): void
    {
        $this->assertSame($this->forwardedMethods(), $this->methodTags());
    }

    /**
     * @return array<string, array{bool, list<string>}>
     */
    private function forwardedMethods(): array
    {
        $wizard = new ReflectionClass(ElasticQueryWizard::class);
        $methods = [];

        foreach ((new ReflectionClass(SearchBuilder::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || str_starts_with($method->getName(), '__') || $wizard->hasMethod($method->getName())) {
                continue;
            }

            $methods[$method->getName()] = in_array($method->getName(), ['when', 'unless'], true)
                ? [true, ['value', 'callback', 'default']]
                : [
                    $this->isFluent($method),
                    array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters()),
                ];
        }

        ksort($methods);

        return $methods;
    }

    private function isFluent(ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        foreach ($returnType instanceof ReflectionUnionType ? $returnType->getTypes() : [$returnType] as $type) {
            if ($type instanceof ReflectionNamedType && (in_array($type->getName(), ['static', 'self'], true) || is_a($type->getName(), SearchBuilder::class, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{bool, list<string>}>
     */
    private function methodTags(): array
    {
        preg_match_all(
            '/@method (\S+) (\w+)\(([^)]*)\)/',
            (string) (new ReflectionClass(ElasticQueryWizard::class))->getDocComment(),
            $tags,
            PREG_SET_ORDER
        );
        $methods = [];

        foreach ($tags as [, $returnType, $name, $parameters]) {
            preg_match_all('/\$(\w+)/', $parameters, $parameterNames);
            $methods[$name] = [$returnType === '$this', $parameterNames[1]];
        }

        ksort($methods);

        return $methods;
    }
}
