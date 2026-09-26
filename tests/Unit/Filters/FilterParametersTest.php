<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class FilterParametersTest extends UnitTestCase
{
    #[Test]
    public function a_misspelled_parameter_is_refused_when_the_filter_is_configured(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "bost" is not supported');

        ElasticFilter::term('name')->withParameters(['bost' => 2]);
    }

    #[Test]
    public function a_parameter_the_query_has_no_setter_for_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no boost() setter');

        ElasticFilter::matchPhrase('name')->withParameters(['boost' => 2]);
    }

    #[Test]
    public function prefix_and_exists_filters_take_a_boost(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'jo', 'has_email' => 'true'])
            ->allowedFilters(
                ElasticFilter::prefix('name')->withParameters(['boost' => 2]),
                ElasticFilter::exists('email', 'has_email')->withParameters(['boost' => 3]),
            );
        $wizard->build();

        $this->assertSame(
            [
                ['prefix' => ['name' => ['value' => 'jo', 'boost' => 2.0]]],
                ['exists' => ['field' => 'email', 'boost' => 3.0]],
            ],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    #[Test]
    public function a_term_filter_takes_only_the_parameters_its_terms_query_also_has(): void
    {
        ElasticFilter::term('name')->withParameters(['boost' => 2]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TermsQuery has no caseInsensitive() setter');

        ElasticFilter::term('name')->withParameters(['case_insensitive' => true]);
    }

    #[Test]
    public function the_query_serializer_and_static_factories_are_not_parameters(): void
    {
        foreach (['to_array', '__construct'] as $name) {
            try {
                ElasticFilter::match('name')->withParameters([$name => true]);
                $this->fail("{$name} was accepted as a parameter.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function snake_case_parameters_reach_the_query_setters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'john'])
            ->allowedFilters(
                ElasticFilter::multiMatch(['name^10', 'country_name^4'], 'name')->withParameters([
                    'type' => 'most_fields',
                    'operator' => 'or',
                    'tie_breaker' => 0.3,
                    'fuzziness' => 'AUTO',
                ])
            );
        $wizard->build();

        $this->assertSame(
            ['fields' => ['name^10', 'country_name^4'], 'query' => 'john', 'type' => 'most_fields', 'operator' => 'or', 'tie_breaker' => 0.3, 'fuzziness' => 'AUTO'],
            $this->getMustQueries($wizard->boolQuery())[0]['multi_match']
        );
    }
}
