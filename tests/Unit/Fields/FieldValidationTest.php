<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Fields;

use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('fields')]
class FieldValidationTest extends UnitTestCase
{
    #[Test]
    public function it_throws_for_invalid_field(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->allowedFields('name')
            ->build();
    }

    #[Test]
    public function it_allows_valid_fields_without_throwing(): void
    {
        $wizard = $this
            ->createElasticWizardWithFields(['testModel' => 'name,id'])
            ->allowedFields('name', 'id');
        $wizard->build();

        $this->assertNotNull($wizard);
    }
}
