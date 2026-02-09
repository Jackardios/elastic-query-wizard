<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Appends;

use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;

/**
 * @group unit
 * @group append
 */
class AppendValidationTest extends UnitTestCase
{
    /** @test */
    public function it_throws_for_invalid_append(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createElasticWizardWithAppends('random-attribute')
            ->allowedAppends('fullname')
            ->build();
    }

    /** @test */
    public function the_exception_contains_unknown_and_allowed_appends(): void
    {
        $exception = new InvalidAppendQuery(collect(['unknown']), collect(['allowed']));

        $this->assertEquals(['unknown'], $exception->unknownAppends->all());
        $this->assertEquals(['allowed'], $exception->allowedAppends->all());
    }

    /** @test */
    public function it_allows_valid_appends_without_throwing(): void
    {
        $wizard = $this
            ->createElasticWizardWithAppends('fullname', AppendModel::class)
            ->allowedAppends('fullname');
        $wizard->build();

        $this->assertNotNull($wizard);
    }
}
