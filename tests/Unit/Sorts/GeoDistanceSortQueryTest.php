<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\Sorts\GeoDistanceSort;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group sort
 */
class GeoDistanceSortQueryTest extends UnitTestCase
{
    /** @test */
    public function it_sorts_by_geo_distance_ascending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('distance')
            ->allowedSorts(GeoDistanceSort::make('location', 55.75, 37.62, 'distance'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'asc',
                'unit' => 'km',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_sorts_by_geo_distance_descending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-distance')
            ->allowedSorts(GeoDistanceSort::make('location', 55.75, 37.62, 'distance'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'desc',
                'unit' => 'km',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_custom_unit(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('distance')
            ->allowedSorts(
                GeoDistanceSort::make('location', 55.75, 37.62, 'distance')->unit('mi')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'asc',
                'unit' => 'mi',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_mode(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('distance')
            ->allowedSorts(
                GeoDistanceSort::make('location', 55.75, 37.62, 'distance')->mode('avg')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'asc',
                'unit' => 'km',
                'mode' => 'avg',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_distance_type(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('distance')
            ->allowedSorts(
                GeoDistanceSort::make('location', 55.75, 37.62, 'distance')->distanceType('plane')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'asc',
                'unit' => 'km',
                'distance_type' => 'plane',
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_ignore_unmapped(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('distance')
            ->allowedSorts(
                GeoDistanceSort::make('location', 55.75, 37.62, 'distance')->ignoreUnmapped()
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'asc',
                'unit' => 'km',
                'ignore_unmapped' => true,
            ],
        ], $sorts[0]);
    }

    /** @test */
    public function it_applies_all_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('distance')
            ->allowedSorts(
                GeoDistanceSort::make('location', 55.75, 37.62, 'distance')
                    ->unit('mi')
                    ->mode('min')
                    ->distanceType('arc')
                    ->ignoreUnmapped()
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals([
            '_geo_distance' => [
                'location' => ['lat' => 55.75, 'lon' => 37.62],
                'order' => 'asc',
                'unit' => 'mi',
                'mode' => 'min',
                'distance_type' => 'arc',
                'ignore_unmapped' => true,
            ],
        ], $sorts[0]);
    }
}
