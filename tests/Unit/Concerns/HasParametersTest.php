<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Concerns;

use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\EsScoutDriver\Enums\Operator;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class HasParametersTest extends TestCase
{
    #[Test]
    public function it_applies_parameters_to_the_query(): void
    {
        $trait = $this->createTraitUser([MatchQuery::class]);
        $trait->withParameters(['boost' => 1.5, 'fuzziness' => 'AUTO']);

        $query = new MatchQuery('title', 'apple');

        $this->assertSame($query, $trait->applyTo($query));
        $this->assertSame(
            ['match' => ['title' => ['query' => 'apple', 'fuzziness' => 'AUTO', 'boost' => 1.5]]],
            $query->toArray()
        );
    }

    #[Test]
    public function it_merges_parameters_and_converts_snake_case_to_camel_case(): void
    {
        $trait = $this->createTraitUser([MatchQuery::class]);
        $trait->withParameters(['boost' => 1.5]);
        $trait->withParameters(['zero_terms_query' => 'all']);

        $query = $trait->applyTo(new MatchQuery('title', 'apple'));

        $this->assertSame(
            ['match' => ['title' => ['query' => 'apple', 'boost' => 1.5, 'zero_terms_query' => 'all']]],
            $query->toArray()
        );
    }

    #[Test]
    public function with_parameters_returns_self(): void
    {
        $trait = $this->createTraitUser([MatchQuery::class]);

        $this->assertSame($trait, $trait->withParameters(['boost' => 1.5]));
    }

    #[Test]
    public function a_name_without_a_setter_is_refused_when_configured(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter `nonExistentMethod` is not supported by');

        $this->createTraitUser([MatchQuery::class])->withParameters(['nonExistentMethod' => 'value']);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function valuesOfTheSetterType(): array
    {
        return [
            'float' => ['boost', 1.5],
            'int for a float' => ['boost', 2],
            'string of a union' => ['operator', 'and'],
            'enum of a union' => ['operator', Operator::And],
            'int of a union' => ['fuzziness', 2],
            'bool' => ['lenient', false],
        ];
    }

    #[Test]
    #[DataProvider('valuesOfTheSetterType')]
    public function a_value_of_the_setter_type_is_accepted(string $name, mixed $value): void
    {
        $trait = $this->createTraitUser([MatchQuery::class]);

        $this->assertSame($trait, $trait->withParameters([$name => $value]));
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function valuesOfAnotherType(): array
    {
        return [
            'numeric string for a float' => ['boost', '2', 'expects float, got string'],
            'null for a float' => ['boost', null, 'expects float, got null'],
            'string for a bool' => ['lenient', 'true', 'expects bool, got string'],
            'float for a union' => ['fuzziness', 1.5, 'got float'],
            'array for a string' => ['analyzer', ['standard'], 'expects string, got array'],
        ];
    }

    #[Test]
    #[DataProvider('valuesOfAnotherType')]
    public function a_value_of_another_type_is_refused_when_configured(string $name, mixed $value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter `{$name}` of ");
        $this->expectExceptionMessage($message);

        $this->createTraitUser([MatchQuery::class])->withParameters([$name => $value]);
    }

    #[Test]
    public function every_query_class_must_take_the_value(): void
    {
        $trait = $this->createTraitUser([TermQuery::class, TermsQuery::class]);

        $this->assertSame($trait, $trait->withParameters(['boost' => 2.0]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter `boost` of ');

        $trait->withParameters(['boost' => 'high']);
    }

    /**
     * @param  list<class-string>  $queryClasses
     */
    private function createTraitUser(array $queryClasses): object
    {
        return new class($queryClasses)
        {
            use HasParameters;

            /**
             * @param  list<class-string>  $queryClasses
             */
            public function __construct(private array $queryClasses) {}

            protected function parameterQueryClasses(): array
            {
                return $this->queryClasses;
            }

            public function applyTo(object $queryBuilder): object
            {
                return $this->applyParametersOnQuery($queryBuilder);
            }
        };
    }
}
