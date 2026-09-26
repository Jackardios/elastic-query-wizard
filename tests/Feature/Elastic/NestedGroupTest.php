<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Filters\NestedFilter;
use Jackardios\ElasticQueryWizard\Groups\NestedGroup;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\NestedModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Search\Hit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('group')]
class NestedGroupTest extends TestCase
{
    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = [
            'A' => NestedModel::factory()->create([
                'title' => 'Product A',
                'comments' => [
                    ['author' => 'john', 'text' => 'Great product', 'rating' => 5],
                    ['author' => 'jane', 'text' => 'Good value', 'rating' => 4],
                ],
            ])->id,
            'B' => NestedModel::factory()->create([
                'title' => 'Product B',
                'comments' => [['author' => 'mike', 'text' => 'Nice item', 'rating' => 3]],
            ])->id,
            'C' => NestedModel::factory()->create([
                'title' => 'Product C',
                'comments' => [['author' => 'john', 'text' => 'Could be better', 'rating' => 2]],
            ])->id,
        ];
    }

    #[Test]
    public function a_nested_group_matches_its_children_on_the_same_nested_document(): void
    {
        $filter = ['author' => 'jane', 'rating' => '5'];

        $this->assertSame([], $this->names($this->hits($filter, $this->commentsGroup())));
        $this->assertSame(['A'], $this->names($this->hits($filter, NestedFilter::make('comments', 'author'), NestedFilter::make('comments', 'rating'))));
    }

    #[Test]
    public function a_nested_group_without_requested_children_matches_every_document(): void
    {
        $this->assertSame(['A', 'B', 'C'], $this->names($this->hits([], $this->commentsGroup())));
    }

    #[Test]
    public function inner_hits_return_the_matching_nested_documents(): void
    {
        $hits = $this->hits(['author' => 'john'], $this->commentsGroup()->innerHits());

        $this->assertSame(['A', 'C'], $this->names($hits));

        foreach ($hits as $hit) {
            $comments = $hit->innerHits()->get('comments');

            $this->assertNotNull($comments);
            $this->assertSame(['john'], $comments->map(fn (Hit $comment): mixed => $comment->source['author'])->all());
        }
    }

    #[Test]
    public function named_inner_hits_are_returned_under_their_name(): void
    {
        $hits = $this->hits(['rating' => '4'], $this->commentsGroup()->innerHits(['name' => 'four_star', 'size' => 1]));

        $this->assertSame(['A'], $this->names($hits));
        $this->assertSame(['four_star'], $hits[0]->innerHits()->keys()->all());
        $this->assertSame('jane', $hits[0]->innerHits()->get('four_star')?->first()?->source['author']);
    }

    private function commentsGroup(): NestedGroup
    {
        return ElasticGroup::nested('comments')->children([
            ElasticFilter::term('comments.author', 'author'),
            ElasticFilter::term('comments.rating', 'rating'),
        ]);
    }

    /**
     * @param  array<string, string>  $filter
     * @return list<Hit>
     */
    private function hits(array $filter, NestedGroup|NestedFilter ...$filters): array
    {
        return $this
            ->createElasticWizardFromQuery(['filter' => $filter], NestedModel::class)
            ->allowedFilters(...$filters)
            ->build()
            ->execute()
            ->hits()
            ->values()
            ->all();
    }

    /**
     * @param  list<Hit>  $hits
     * @return list<string>
     */
    private function names(array $hits): array
    {
        $ids = array_map(fn (Hit $hit): int => (int) $hit->documentId, $hits);
        $names = array_keys(array_intersect($this->ids, $ids));
        sort($names);

        return $names;
    }
}
