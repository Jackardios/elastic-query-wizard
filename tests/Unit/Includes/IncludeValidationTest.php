<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Includes;

use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('include')]
class IncludeValidationTest extends UnitTestCase
{
    #[Test]
    public function it_throws_for_invalid_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createElasticWizardWithIncludes('random-model')
            ->allowedIncludes('relatedModels')
            ->build();
    }

    #[Test]
    public function the_exception_contains_unknown_and_allowed_includes(): void
    {
        $exception = new InvalidIncludeQuery(collect(['unknown']), collect(['allowed']));

        $this->assertEquals(['unknown'], $exception->unknownIncludes->all());
        $this->assertEquals(['allowed'], $exception->allowedIncludes->all());
    }

    #[Test]
    public function it_stores_allowed_includes(): void
    {
        $query = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels', 'relatedModels');

        $this->assertSame(['relatedModels.nestedRelatedModels', 'relatedModels'], array_keys($query->getAllowedIncludes()));
    }

    #[Test]
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
