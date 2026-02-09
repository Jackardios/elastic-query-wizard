<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\Sorts\ScriptSort;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group sort
 */
class ScriptSortQueryTest extends UnitTestCase
{
    /** @test */
    public function it_sorts_by_script_ascending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('custom')
            ->allowedSorts(ScriptSort::make("doc['price'].value * 1.1", 'custom'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['price'].value * 1.1",
                ],
                'order' => 'asc',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_sorts_by_script_descending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-custom')
            ->allowedSorts(ScriptSort::make("doc['price'].value * 1.1", 'custom'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['price'].value * 1.1",
                ],
                'order' => 'desc',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_custom_type(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('custom')
            ->allowedSorts(
                ScriptSort::make("doc['title'].value.toLowerCase()", 'custom')->type('string')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_script' => [
                'type' => 'string',
                'script' => [
                    'source' => "doc['title'].value.toLowerCase()",
                ],
                'order' => 'asc',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_params(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('custom')
            ->allowedSorts(
                ScriptSort::make("doc['price'].value * params.factor", 'custom')
                    ->params(['factor' => 1.2])
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['price'].value * params.factor",
                    'params' => ['factor' => 1.2],
                ],
                'order' => 'asc',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_mode(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('custom')
            ->allowedSorts(
                ScriptSort::make("doc['scores'].stream().sum()", 'custom')->mode('avg')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['scores'].stream().sum()",
                ],
                'order' => 'asc',
                'mode' => 'avg',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_all_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('custom')
            ->allowedSorts(
                ScriptSort::make("doc['price'].value * params.factor + params.bonus", 'custom')
                    ->type('number')
                    ->params(['factor' => 1.5, 'bonus' => 100])
                    ->mode('sum')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['price'].value * params.factor + params.bonus",
                    'params' => ['factor' => 1.5, 'bonus' => 100],
                ],
                'order' => 'asc',
                'mode' => 'sum',
            ],
        ], $sorts[0]);
    }
}
