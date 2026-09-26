<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\ExistsFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class ExistsFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_adds_a_filter_clause_for_truthy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'true'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $filterQueries[0]);
        $this->assertEmpty($mustNotQueries);
    }

    #[Test]
    public function it_adds_a_must_not_clause_for_falsy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'false'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $mustNotQueries[0]);
    }

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => ''])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertEmpty($mustNotQueries);
    }

    #[Test]
    public function it_rejects_a_value_that_is_not_a_boolean(): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a boolean');

        $this
            ->createElasticWizardWithFilters(['has_image' => 'definitely'])
            ->allowedFilters(ExistsFilter::make('has_image'))
            ->build();
    }

    #[Test]
    public function it_adds_a_filter_clause_for_integer_1(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => '1'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $filterQueries[0]);
    }

    #[Test]
    public function it_adds_a_must_not_clause_for_integer_0(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => '0'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $mustNotQueries[0]);
    }

    #[Test]
    public function a_falsy_value_in_should_is_an_alternative_to_its_siblings(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'false'])
            ->allowedFilters(ExistsFilter::make('has_image')->inShould());
        $wizard->build();

        $this->assertSame(
            [['bool' => ['must_not' => [['exists' => ['field' => 'has_image']]]]]],
            $this->getShouldQueries($wizard->boolQuery())
        );
        $this->assertEmpty($this->getMustNotQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_falsy_value_in_must_not_requires_the_field(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'false'])
            ->allowedFilters(ExistsFilter::make('has_image')->inMustNot());
        $wizard->build();

        $this->assertSame([['exists' => ['field' => 'has_image']]], $this->getFilterQueries($wizard->boolQuery()));
        $this->assertEmpty($this->getMustNotQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_truthy_value_in_must_not_excludes_the_field(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'true'])
            ->allowedFilters(ExistsFilter::make('has_image')->inMustNot());
        $wizard->build();

        $this->assertSame([['exists' => ['field' => 'has_image']]], $this->getMustNotQueries($wizard->boolQuery()));
        $this->assertEmpty($this->getFilterQueries($wizard->boolQuery()));
    }
}
