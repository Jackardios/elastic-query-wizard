<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Appends;

use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('append')]
class AppendValidationTest extends UnitTestCase
{
    #[Test]
    public function it_throws_for_invalid_append(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createElasticWizardWithAppends('random-attribute')
            ->allowedAppends('fullname')
            ->build();
    }

    #[Test]
    public function the_exception_contains_unknown_and_allowed_appends(): void
    {
        $exception = new InvalidAppendQuery(collect(['unknown']), collect(['allowed']));

        $this->assertEquals(['unknown'], $exception->unknownAppends->all());
        $this->assertEquals(['allowed'], $exception->allowedAppends->all());
    }

    #[Test]
    public function it_allows_valid_appends_without_throwing(): void
    {
        $wizard = $this
            ->createElasticWizardWithAppends('fullname', AppendModel::class)
            ->allowedAppends('fullname');
        $wizard->build();

        $this->assertNotNull($wizard);
    }
}
