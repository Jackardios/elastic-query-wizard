<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Groups\BoolGroup;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('group')]
class GroupTest extends TestCase
{
    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = [
            'x-a' => TestModel::factory()->create(['category' => 'x', 'name' => 'a'])->id,
            'x-b' => TestModel::factory()->create(['category' => 'x', 'name' => 'b'])->id,
            'y-a' => TestModel::factory()->create(['category' => 'y', 'name' => 'a'])->id,
        ];
    }

    #[Test]
    public function a_minimum_should_match_without_a_requested_should_child_keeps_the_other_conditions(): void
    {
        $this->assertSame(['x-a', 'x-b'], $this->search(['category' => 'x'], $this->orGroup()));
    }

    #[Test]
    public function a_minimum_should_match_requires_one_of_the_requested_should_children(): void
    {
        $this->assertSame(['x-a'], $this->search(['category' => 'x', 'name' => 'a'], $this->orGroup()));
    }

    #[Test]
    public function a_group_without_requested_children_matches_every_document(): void
    {
        $this->assertSame(['x-a', 'x-b', 'y-a'], $this->search([], $this->orGroup()));
    }

    private function orGroup(): BoolGroup
    {
        return ElasticGroup::bool('any')->minimumShouldMatch(1)->children([
            ElasticFilter::term('category'),
            ElasticFilter::term('name.keyword')->alias('name')->inShould(),
        ]);
    }

    /**
     * @param  array<string, string>  $filter
     * @return list<string>
     */
    private function search(array $filter, BoolGroup $group): array
    {
        $ids = $this
            ->createElasticWizardFromQuery(['filter' => $filter])
            ->allowedFilters($group)
            ->build()
            ->execute()
            ->models()
            ->pluck('id')
            ->all();

        $names = array_keys(array_intersect($this->ids, $ids));
        sort($names);

        return $names;
    }
}
