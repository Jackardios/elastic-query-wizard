<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{\Closure(): FilterInterface}>
     */
    public static function filtersWithoutALimitOfTheirOwn(): array
    {
        return [
            'wildcard' => [static fn () => ElasticFilter::wildcard('name')],
            'match' => [static fn () => ElasticFilter::match('name')],
            'matchPhrase' => [static fn () => ElasticFilter::matchPhrase('name')],
            'matchPhrasePrefix' => [static fn () => ElasticFilter::matchPhrasePrefix('name')],
            'multiMatch' => [static fn () => ElasticFilter::multiMatch('name', ['name'])],
            'queryString' => [static fn () => ElasticFilter::queryString('name')],
            'simpleQueryString' => [static fn () => ElasticFilter::simpleQueryString('name')],
        ];
    }

    #[Test]
    #[DataProvider('filtersWithoutALimitOfTheirOwn')]
    public function a_text_or_pattern_filter_is_limited_to_1000_characters_by_default(\Closure $make): void
    {
        $this->assertTrue($this->accepts($make(), str_repeat('a', 1000)));

        try {
            $this->accepts($make(), str_repeat('a', 1001));
            $this->fail('The filter accepted a value over the default limit.');
        } catch (InvalidFilterValue $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertStringContainsString('Expected at most 1000 characters, got 1001.', $exception->getMessage());
        }
    }

    #[Test]
    #[DataProvider('filtersWithoutALimitOfTheirOwn')]
    public function max_length_changes_or_removes_the_default_limit(\Closure $make): void
    {
        $this->assertTrue($this->accepts($make()->maxLength(null), str_repeat('a', 5000)));
        $this->assertTrue($this->accepts($make()->maxLength(5000), str_repeat('a', 5000)));

        $this->expectException(InvalidFilterValue::class);

        $this->accepts($make()->maxLength(3), 'abcd');
    }

    #[Test]
    public function the_config_changes_the_default_limit_of_the_filters_without_their_own(): void
    {
        config()->set('elastic-query-wizard.max_text_length', '20');

        $this->assertTrue($this->accepts(ElasticFilter::match('name'), str_repeat('a', 20)));
        $this->assertTrue($this->accepts(ElasticFilter::regexp('name'), str_repeat('a', 1000)));
        $this->assertTrue($this->accepts(ElasticFilter::match('name')->maxLength(30), str_repeat('a', 30)));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected at most 20 characters, got 21.');

        $this->accepts(ElasticFilter::match('name'), str_repeat('a', 21));
    }

    #[Test]
    public function a_null_config_removes_the_default_limit_but_not_a_filters_own(): void
    {
        config()->set('elastic-query-wizard.max_text_length', null);

        $this->assertTrue($this->accepts(ElasticFilter::wildcard('name'), str_repeat('a', 5000)));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected at most 256 characters, got 257.');

        $this->accepts(ElasticFilter::fuzzy('name'), str_repeat('a', 257));
    }

    #[Test]
    public function a_config_limit_that_is_not_a_positive_integer_throws(): void
    {
        config()->set('elastic-query-wizard.max_text_length', 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config `elastic-query-wizard.max_text_length` must be a positive integer or null.');

        $this->accepts(ElasticFilter::match('name'), 'a');
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

    #[Test]
    public function more_like_this_takes_a_long_text_unless_max_length_limits_it(): void
    {
        config()->set('elastic-query-wizard.max_text_length', 20);

        $this->assertTrue($this->accepts(ElasticFilter::moreLikeThis('name', ['title']), str_repeat('a', 5000)));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected at most 3 characters, got 4.');

        $this->accepts(ElasticFilter::moreLikeThis('name', ['title'])->maxLength(3), 'abcd');
    }
}
