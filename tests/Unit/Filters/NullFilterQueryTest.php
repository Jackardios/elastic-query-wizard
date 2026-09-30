<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\NullFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class NullFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_adds_a_must_not_clause_for_truthy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'true'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $mustNotQueries[0]);
    }

    #[Test]
    public function it_adds_a_filter_clause_for_falsy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'false'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $filterQueries[0]);
        $this->assertEmpty($mustNotQueries);
    }

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => ''])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
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
            ->createElasticWizardWithFilters(['is_null' => 'invalid'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'))
            ->build();
    }

    #[Test]
    public function it_adds_a_must_not_clause_for_integer_1(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => '1'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $mustNotQueries[0]);
    }

    #[Test]
    public function it_adds_a_filter_clause_for_integer_0(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => '0'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $filterQueries[0]);
    }

    #[Test]
    public function not_null_filter_handles_truthy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => 'true'])
            ->allowedFilters(NullFilter::notNull('thumbnail', 'has_value'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Inverted: truthy means NOT NULL (field exists)
        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $filterQueries[0]);
        $this->assertEmpty($mustNotQueries);
    }

    #[Test]
    public function not_null_filter_handles_falsy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => 'false'])
            ->allowedFilters(NullFilter::notNull('thumbnail', 'has_value'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Inverted: falsy means NULL (field doesn't exist)
        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $mustNotQueries[0]);
    }

    #[Test]
    public function not_null_filter_handles_integer_1(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => '1'])
            ->allowedFilters(NullFilter::notNull('thumbnail', 'has_value'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        // Inverted: 1 means NOT NULL (field exists)
        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $filterQueries[0]);
    }

    #[Test]
    public function not_null_filter_handles_integer_0(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => '0'])
            ->allowedFilters(NullFilter::notNull('thumbnail', 'has_value'));
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Inverted: 0 means NULL (field doesn't exist)
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $mustNotQueries[0]);
    }

    #[Test]
    public function a_truthy_value_in_should_is_an_alternative_to_its_siblings(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'true'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null')->inShould());
        $wizard->build();

        $this->assertSame(
            [['bool' => ['must_not' => [['exists' => ['field' => 'deleted_at']]]]]],
            $this->getShouldQueries($wizard->boolQuery())
        );
        $this->assertEmpty($this->getMustNotQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_truthy_value_in_must_not_requires_the_field(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'true'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null')->inMustNot());
        $wizard->build();

        $this->assertSame([['exists' => ['field' => 'deleted_at']]], $this->getFilterQueries($wizard->boolQuery()));
        $this->assertEmpty($this->getMustNotQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_falsy_value_in_must_not_excludes_the_field(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'false'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null')->inMustNot());
        $wizard->build();

        $this->assertSame([['exists' => ['field' => 'deleted_at']]], $this->getMustNotQueries($wizard->boolQuery()));
        $this->assertEmpty($this->getFilterQueries($wizard->boolQuery()));
    }
}
