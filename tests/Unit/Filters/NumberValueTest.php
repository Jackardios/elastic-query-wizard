<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class NumberValueTest extends UnitTestCase
{
    #[Test]
    public function a_term_filter_reading_numbers_sends_numbers(): void
    {
        $this->assertSame(
            [['term' => ['id' => ['value' => 5]]]],
            $this->filterQueries(ElasticFilter::term('id')->asNumber(), ' 5 ')
        );
        $this->assertSame(
            [['terms' => ['id' => [5, 1.5, '99999999999999999999']]]],
            $this->filterQueries(ElasticFilter::term('id')->asNumber(), ['5', '1.5', '99999999999999999999'])
        );
    }

    #[Test]
    public function an_ids_filter_reading_numbers_sends_them_as_ids(): void
    {
        $this->assertSame(
            [['ids' => ['values' => ['5', '7']]]],
            $this->filterQueries(ElasticFilter::ids('id')->asNumber(), ['05', '7'])
        );
    }

    #[Test]
    public function a_range_filter_reading_numbers_sends_numbers(): void
    {
        $this->assertSame(
            [['range' => ['id' => ['gte' => 5, 'lt' => 7.5]]]],
            $this->filterQueries(ElasticFilter::range('id')->asNumber(), ['gte' => '5', 'lt' => '7.5'])
        );
    }

    /**
     * @return array<string, array{0: FilterInterface, 1: mixed, 2: class-string<InvalidFilterValue>}>
     */
    public static function valuesThatAreNotNumbers(): array
    {
        return [
            'term text' => [ElasticFilter::term('id')->asNumber(), 'abc', InvalidFilterValue::class],
            'term list with text' => [ElasticFilter::term('id')->asNumber(), ['1', 'abc'], InvalidFilterValue::class],
            'term exponent' => [ElasticFilter::term('id')->asNumber(), '1e3', InvalidFilterValue::class],
            'term boolean' => [ElasticFilter::term('id')->asNumber(), true, InvalidFilterValue::class],
            'ids text' => [ElasticFilter::ids('id')->asNumber(), ['1', 'abc'], InvalidFilterValue::class],
            'range date' => [ElasticFilter::range('id')->asNumber(), ['gte' => '2024-01-01'], InvalidRangeValue::class],
            'range text' => [ElasticFilter::range('id')->asNumber(), ['gte' => 'abc'], InvalidRangeValue::class],
            'range date-time default' => [ElasticFilter::range('id')->asNumber()->default(['gte' => new \DateTimeImmutable]), null, InvalidRangeValue::class],
        ];
    }

    #[Test]
    #[DataProvider('valuesThatAreNotNumbers')]
    public function a_value_that_is_not_a_number_is_a_400(FilterInterface $filter, mixed $value, string $exceptionClass): void
    {
        try {
            $this->filterQueries($filter, $value);
            $this->fail('The value was accepted.');
        } catch (InvalidFilterValue $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertStringContainsString('Expected a decimal number', $exception->getMessage());
        }
    }

    #[Test]
    public function without_as_number_text_is_sent_as_it_is(): void
    {
        $this->assertSame(
            [['term' => ['id' => ['value' => 'abc']]]],
            $this->filterQueries(ElasticFilter::term('id'), 'abc')
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filterQueries(FilterInterface $filter, mixed $value): array
    {
        $wizard = $this->createElasticWizardWithFilters($value === null ? [] : ['id' => $value])->allowedFilters($filter);
        $wizard->build();

        return $this->getFilterQueries($wizard->boolQuery());
    }
}
