<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class ValueLengthTest extends UnitTestCase
{
    #[Test]
    public function a_regexp_longer_than_elasticsearch_accepts_by_default_is_a_400(): void
    {
        $this->assertTrue($this->accepts(ElasticFilter::regexp('name'), str_repeat('a', 1000)));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected at most 1000 characters, got 1001.');

        $this->accepts(ElasticFilter::regexp('name'), str_repeat('a', 1001));
    }

    #[Test]
    public function a_prefix_longer_than_elasticsearch_accepts_by_default_is_a_400(): void
    {
        $this->assertTrue($this->accepts(ElasticFilter::prefix('name'), str_repeat('a', 1000)));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected at most 1000 characters, got 1001.');

        $this->accepts(ElasticFilter::prefix('name'), str_repeat('a', 1001));
    }

    #[Test]
    public function a_fuzzy_value_is_limited_to_256_characters_by_default(): void
    {
        $this->assertTrue($this->accepts(ElasticFilter::fuzzy('name'), str_repeat('a', 256)));
        $this->assertTrue($this->accepts(ElasticFilter::fuzzy('name')->maxLength(null), str_repeat('a', 5000)));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected at most 256 characters, got 257.');

        $this->accepts(ElasticFilter::fuzzy('name'), str_repeat('a', 257));
    }

    #[Test]
    public function the_exception_keeps_the_whole_value(): void
    {
        $value = str_repeat('a', 1001);

        try {
            $this->accepts(ElasticFilter::regexp('name'), $value);
            $this->fail('The regexp filter accepted a value over its limit.');
        } catch (InvalidFilterValue $exception) {
            $this->assertSame($value, $exception->filterValue);
        }
    }

    #[Test]
    public function max_length_changes_or_removes_the_regexp_limit(): void
    {
        $this->assertTrue($this->accepts(ElasticFilter::regexp('name')->maxLength(null), str_repeat('a', 5000)));
        $this->assertTrue($this->accepts(ElasticFilter::regexp('name')->maxLength(2000), str_repeat('a', 2000)));
    }

    #[Test]
    public function text_and_pattern_filters_take_an_opt_in_limit(): void
    {
        $filters = [
            ElasticFilter::wildcard('name'),
            ElasticFilter::match('name'),
            ElasticFilter::matchPhrase('name'),
            ElasticFilter::matchPhrasePrefix('name'),
            ElasticFilter::multiMatch('name', ['name']),
            ElasticFilter::queryString('name'),
            ElasticFilter::simpleQueryString('name'),
            ElasticFilter::moreLikeThis('name', ['title']),
        ];

        foreach ($filters as $filter) {
            $this->assertTrue($this->accepts($filter, str_repeat('a', 5000)), $filter::class);

            try {
                $this->accepts($filter->maxLength(3), 'abcd');
                $this->fail($filter::class.' accepted a value over its limit.');
            } catch (InvalidFilterValue $exception) {
                $this->assertSame(400, $exception->getStatusCode());
            }
        }
    }

    #[Test]
    public function the_limit_counts_characters_as_elasticsearch_does(): void
    {
        $this->assertTrue($this->accepts(ElasticFilter::match('name')->maxLength(3), 'äöü'));

        $this->expectException(InvalidFilterValue::class);

        $this->accepts(ElasticFilter::match('name')->maxLength(2), 'a😀');
    }

    #[Test]
    public function the_limit_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ElasticFilter::match('name')->maxLength(0);
    }

    private function accepts(FilterInterface $filter, string $value): bool
    {
        $this->createElasticWizardWithFilters(['name' => $value])->allowedFilters($filter)->build();

        return true;
    }
}
