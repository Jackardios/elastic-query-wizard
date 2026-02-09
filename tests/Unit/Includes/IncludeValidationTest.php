<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Includes;

use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use ReflectionClass;

/**
 * @group unit
 * @group include
 */
class IncludeValidationTest extends UnitTestCase
{
    /** @test */
    public function it_throws_for_invalid_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createElasticWizardWithIncludes('random-model')
            ->allowedIncludes('relatedModels')
            ->build();
    }

    /** @test */
    public function the_exception_contains_unknown_and_allowed_includes(): void
    {
        $exception = new InvalidIncludeQuery(collect(['unknown']), collect(['allowed']));

        $this->assertEquals(['unknown'], $exception->unknownIncludes->all());
        $this->assertEquals(['allowed'], $exception->allowedIncludes->all());
    }

    /** @test */
    public function it_stores_allowed_includes(): void
    {
        $query = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels', 'relatedModels');

        $property = (new ReflectionClass($query))->getProperty('allowedIncludes');
        $rawIncludes = $property->getValue($query);

        $this->assertCount(2, $rawIncludes);
        $this->assertContains('relatedModels', $rawIncludes);
        $this->assertContains('relatedModels.nestedRelatedModels', $rawIncludes);
    }

    /** @test */
    public function it_allows_valid_callback_include_without_throwing(): void
    {
        $wizard = $this
            ->createElasticWizardWithIncludes('myInclude')
            ->allowedIncludes(
                ElasticInclude::callback('myInclude', function ($subject) {
                    return $subject;
                })
            );
        $wizard->build();

        $this->assertNotNull($wizard);
    }
}
