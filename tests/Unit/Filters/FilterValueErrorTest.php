<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoShapeValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A value a filter cannot read is a 400 InvalidFilterValue that names the
 * filter by its public name.
 */
#[Group('unit')]
#[Group('filter')]
class FilterValueErrorTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: FilterInterface, 1: mixed, 2: class-string<InvalidFilterValue>}>
     */
    public static function unreadableValues(): array
    {
        return [
            'range scalar' => [ElasticFilter::range('price', 'cost'), '5', InvalidRangeValue::class],
            'range text bound' => [ElasticFilter::range('price', 'cost'), ['gte' => 'abc'], InvalidRangeValue::class],
            'range date math bound' => [ElasticFilter::range('price', 'cost'), ['gte' => 'now-1d'], InvalidRangeValue::class],
            'range exponent bound' => [ElasticFilter::range('price', 'cost'), ['lt' => '1e3'], InvalidRangeValue::class],
            'range legacy key' => [ElasticFilter::range('price', 'cost'), ['from' => '1'], InvalidRangeValue::class],
            'bbox latitude out of range' => [ElasticFilter::geoBoundingBox('location', 'cost'), ['0', '-100', '1', '1'], InvalidGeoBoundingBoxValue::class],
            'distance latitude out of range' => [ElasticFilter::geoDistance('location', 'cost'), ['lat' => '100', 'lon' => '0', 'distance' => '1km'], InvalidGeoDistanceValue::class],
            'distance longitude out of range' => [ElasticFilter::geoDistance('location', 'cost'), ['lat' => '0', 'lon' => '181', 'distance' => '1km'], InvalidGeoDistanceValue::class],
            'distance text' => [ElasticFilter::geoDistance('location', 'cost'), ['lat' => '0', 'lon' => '0', 'distance' => 'abc'], InvalidGeoDistanceValue::class],
            'distance negative' => [ElasticFilter::geoDistance('location', 'cost'), ['lat' => '0', 'lon' => '0', 'distance' => '-5km'], InvalidGeoDistanceValue::class],
            'distance zero' => [ElasticFilter::geoDistance('location', 'cost'), ['lat' => '0', 'lon' => '0', 'distance' => '0km'], InvalidGeoDistanceValue::class],
            'distance unit in capitals' => [ElasticFilter::geoDistance('location', 'cost'), ['lat' => '0', 'lon' => '0', 'distance' => '3KM'], InvalidGeoDistanceValue::class],
            'shape type' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'circle'], InvalidGeoShapeValue::class],
            'shape point latitude out of range' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'point', 'coordinates' => ['10', '100']], InvalidGeoShapeValue::class],
            'shape point with three numbers' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'point', 'coordinates' => ['10', '10', '5']], InvalidGeoShapeValue::class],
            'shape envelope corner with one number' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'envelope', 'coordinates' => [['1'], ['2']]], InvalidGeoShapeValue::class],
            'shape envelope top below bottom' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'envelope', 'coordinates' => [['0', '0'], ['20', '50']]], InvalidGeoShapeValue::class],
            'shape envelope longitude out of range' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'envelope', 'coordinates' => [['-200', '10'], ['20', '0']]], InvalidGeoShapeValue::class],
            'shape polygon point with one number' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'polygon', 'coordinates' => [[['1'], ['2'], ['3'], ['1']]]], InvalidGeoShapeValue::class],
            'shape polygon longitude out of range' => [ElasticFilter::geoShape('boundary', 'cost'), ['type' => 'polygon', 'coordinates' => [[['0', '0'], ['200', '0'], ['0', '10']]]], InvalidGeoShapeValue::class],
            'exists' => [ElasticFilter::exists('price', 'cost'), 'maybe', InvalidFilterValue::class],
            'null' => [ElasticFilter::null('price', 'cost'), 'maybe', InvalidFilterValue::class],
            'match boolean' => [ElasticFilter::match('title', 'cost'), true, InvalidFilterValue::class],
            'match phrase boolean' => [ElasticFilter::matchPhrase('title', 'cost'), false, InvalidFilterValue::class],
            'match phrase prefix boolean' => [ElasticFilter::matchPhrasePrefix('title', 'cost'), true, InvalidFilterValue::class],
            'multi match boolean' => [ElasticFilter::multiMatch(['title'], 'cost'), true, InvalidFilterValue::class],
            'prefix boolean' => [ElasticFilter::prefix('title', 'cost'), true, InvalidFilterValue::class],
            'wildcard boolean' => [ElasticFilter::wildcard('title', 'cost'), true, InvalidFilterValue::class],
            'regexp boolean' => [ElasticFilter::regexp('title', 'cost'), true, InvalidFilterValue::class],
            'fuzzy boolean' => [ElasticFilter::fuzzy('title', 'cost'), true, InvalidFilterValue::class],
            'query string boolean' => [ElasticFilter::queryString('title', 'cost'), true, InvalidFilterValue::class],
            'simple query string boolean' => [ElasticFilter::simpleQueryString('title', 'cost'), true, InvalidFilterValue::class],
            'ids boolean' => [ElasticFilter::ids('cost'), true, InvalidFilterValue::class],
            'ids list with a boolean' => [ElasticFilter::ids('cost'), ['1', false], InvalidFilterValue::class],
        ];
    }

    #[Test]
    #[DataProvider('unreadableValues')]
    public function an_unreadable_value_is_a_400_naming_the_filter(FilterInterface $filter, mixed $value, string $exceptionClass): void
    {
        try {
            $this->createElasticWizardWithFilters(['cost' => $value])->allowedFilters($filter)->build();
            $this->fail('The value was accepted.');
        } catch (InvalidFilterValue $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('cost', $exception->filterName);
            $this->assertStringContainsString('for filter `cost`', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unreadableTrashedModes(): array
    {
        return [
            'one' => ['1'],
            'zero' => ['0'],
            'text' => ['everything'],
        ];
    }

    #[Test]
    #[DataProvider('unreadableTrashedModes')]
    public function an_unreadable_trashed_mode_is_a_400(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected one of: with, only, without, true, false');

        $this->createElasticWizardWithFilters(['trashed' => $value], SoftDeleteModel::class)
            ->allowedFilters(ElasticFilter::trashed())
            ->build();
    }

    #[Test]
    public function range_bounds_are_numbers_or_iso_dates_passed_on_as_sent(): void
    {
        $wizard = $this->createElasticWizardWithFilters([
            'price' => ['gte' => ' 100 ', 'lt' => '99.5'],
            'created_at' => ['gte' => '2024-01-01', 'lte' => '2024-01-31T10:00:00+03:00'],
        ])->allowedFilters(ElasticFilter::range('price'), ElasticFilter::range('created_at'));
        $wizard->build();

        $this->assertSame([
            ['range' => ['price' => ['gte' => 100, 'lt' => 99.5]]],
            ['range' => ['created_at' => ['gte' => '2024-01-01', 'lte' => '2024-01-31T10:00:00+03:00']]],
        ], $this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_distance_takes_a_positive_number_with_an_optional_unit(): void
    {
        foreach (['3km', '3 km', '1.5mi', '.5km', '250', '2NM'] as $distance) {
            $wizard = $this->createElasticWizardWithFilters(['location' => ['lat' => '55.7', 'lon' => '37.6', 'distance' => $distance]])
                ->allowedFilters(ElasticFilter::geoDistance('location'));
            $wizard->build();

            $this->assertSame($distance, $this->getFilterQueries($wizard->boolQuery())[0]['geo_distance']['distance']);
        }
    }
}
