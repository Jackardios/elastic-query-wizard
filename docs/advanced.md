# Advanced Usage

This section covers advanced features and customization options for Elastic Query Wizard.

> **Note:** Code examples in this document assume the following imports:
> ```php
> use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
> use Jackardios\ElasticQueryWizard\ElasticFilter;
> use Jackardios\ElasticQueryWizard\ElasticSort;
> use Jackardios\ElasticQueryWizard\ElasticInclude;
> use Jackardios\ElasticQueryWizard\ElasticQuery;
> use Jackardios\ElasticQueryWizard\ElasticAggregation;
> use Jackardios\EsScoutDriver\Search\SearchResult;
> use Illuminate\Database\Eloquent\Builder;
> use Illuminate\Database\Eloquent\Collection;
> ```

## Table of Contents

- [Accessing the SearchBuilder](#accessing-the-searchbuilder)
- [Declarative SearchBuilder Methods](#declarative-searchbuilder-methods)
- [DSL Proxies](#dsl-proxies)
- [Custom Aggregations](#custom-aggregations)
- [Working with Bool Query](#working-with-bool-query)
- [Modify Query Callbacks](#modify-query-callbacks)
- [Modify Models Callbacks](#modify-models-callbacks)
- [Resource Schemas](#resource-schemas)
- [Fields and Appends](#fields-and-appends)
- [Creating Custom Filters](#creating-custom-filters)
- [Creating Custom Sorts](#creating-custom-sorts)
- [Creating Custom Includes](#creating-custom-includes)

## Accessing the SearchBuilder

After building the wizard, you can access the underlying `SearchBuilder` for any low-level functionality not covered by helper methods:

```php
$wizard = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
    ])
    ->build();

// Access the SearchBuilder
$searchBuilder = $wizard->getSubject();

// Add custom query
$searchBuilder->must(ElasticQuery::matchPhrase('content', 'exact phrase'));

// Execute and get results
$results = $wizard->execute();
```

---

## Declarative SearchBuilder Methods

`ElasticQueryWizard` exposes most useful `SearchBuilder` operations directly.
These methods are declarative: you can call them before or after `build()`, and they remain consistent with wizard configuration.

```php
$results = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
    ])
    ->query(ElasticQuery::match('language', 'en'))
    ->must(ElasticQuery::range('published_at')->gte('2024-01-01'))
    ->highlight('title')
    ->aggregate('by_author', ElasticAggregation::terms('author')->size(10))
    ->from(0)
    ->size(20)
    ->build()
    ->execute();
```

You can also use `tapSearchBuilder()` to apply arbitrary mutations:

```php
ElasticQueryWizard::for(Post::class)
    ->tapSearchBuilder(function ($builder) {
        $builder->trackTotalHits(true);
        $builder->minScore(0.5);
    });
```

---

## DSL Proxies

To keep API style consistent inside this package, use:

- `ElasticQuery` as a proxy to `Jackardios\EsScoutDriver\Support\Query`
- `ElasticAggregation` as a proxy to `Jackardios\EsScoutDriver\Aggregations\Agg`

```php
ElasticQuery::multiMatch(['title^2', 'body'], 'laravel search');
ElasticAggregation::stats('price');
```

Both proxies expose the full underlying `es-scout-driver` factory surface.

---

## Custom Aggregations

Add Elasticsearch aggregations to collect analytics alongside search results:

```php
$wizard = ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        ElasticFilter::term('category'),
        ElasticFilter::range('price'),
    ])
    ->aggregate('categories', ElasticAggregation::terms('category')->size(20))
    ->aggregate('price_stats', ElasticAggregation::stats('price'))
    ->aggregate('price_histogram', ElasticAggregation::histogram('price', 100))
    ->build();

$results = $wizard->execute();

// Get aggregation results
$aggregations = $results->aggregations();
$categories = $aggregations['categories']['buckets'];
$priceStats = $aggregations['price_stats'];
```

### Nested Aggregations

```php
$wizard->aggregate(
    'categories',
    ElasticAggregation::terms('category')
        ->agg('avg_price', ElasticAggregation::avg('price'))
        ->agg('brands', ElasticAggregation::terms('brand')->size(5))
);
```

---

## Working with Bool Query

Access the root bool query for complex query logic:

```php
$wizard = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
    ])
    ->build();

// Access the bool query directly
$boolQuery = $wizard->boolQuery();

// Add must clause
$boolQuery->addMust(ElasticQuery::match('title', 'search term'));

// Add should clause with minimum_should_match
$boolQuery->addShould(ElasticQuery::term('is_featured', true));
$boolQuery->addShould(ElasticQuery::range('views')->gte(1000));
$boolQuery->minimumShouldMatch(1);

// Add must_not clause
$boolQuery->addMustNot(ElasticQuery::term('is_hidden', true));
```

---

## Modify Query Callbacks

Add callbacks that modify the Eloquent query before loading models.
This API is consistent with `SearchBuilder::modifyQuery()` from `es-scout-driver`:
the second callback argument receives raw Elasticsearch response array.
Register callbacks before calling `build()`.

```php
$wizard = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::match('title'),
    ])
    ->modifyQuery(function (Builder $builder, array $rawResult) {
        // Add additional Eloquent constraints
        $builder->where('is_published', true);

        // Access Elasticsearch result metadata
        $hits = $rawResult['hits']['hits'] ?? [];
        $total = $rawResult['hits']['total']['value'] ?? 0;
    })
    ->build();
```

### Use Cases

#### Adding Scopes

```php
->modifyQuery(function (Builder $builder, array $rawResult) {
    $builder->withoutGlobalScope('active');
})
```

#### Custom Eager Loading

```php
->modifyQuery(function (Builder $builder, array $rawResult) {
    $builder->with(['author' => function ($query) {
        $query->select('id', 'name', 'avatar');
    }]);
})
```

---

## Modify Models Callbacks

Add callbacks that transform the collection of models after they're loaded.
This API is consistent with `SearchBuilder::modifyModels()` from `es-scout-driver`:
Register callbacks before calling `build()`.

```php
$wizard = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::match('title'),
    ])
    ->modifyModels(function (Collection $collection) {
        // Transform the collection
        return $collection->map(function ($post) {
            $post->computed_field = calculateSomething($post);
            return $post;
        });
    })
    ->build();
```

### Use Cases

#### Adding Computed Properties

```php
->modifyModels(function (Collection $collection) {
    return $collection->each(function ($model) {
        $model->setAttribute('score', $model->likes * 2 + $model->views);
    });
})
```

#### Filtering Results

```php
->modifyModels(function (Collection $collection) {
    return $collection->filter(function ($model) {
        return $model->canBeViewed(auth()->user());
    })->values();
})
```

---

## Resource Schemas

Resource schemas centralize query configuration in a reusable class. This is especially useful when the same model is queried from multiple endpoints or when you want to share configuration between different wizard types.

### Creating a Schema

Extend `ResourceSchema` and implement the required `model()` method. All other methods are optional.

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;

class PostSchema extends ResourceSchema
{
    public function model(): string
    {
        return Post::class;
    }

    /**
     * Resource type for sparse fieldsets (?fields[post]=id,title).
     * Defaults to camelCase of model basename.
     */
    public function type(): string
    {
        return 'post';
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        return [
            'status',
            ElasticFilter::match('title'),
            ElasticFilter::range('created_at'),
            ElasticFilter::multiMatch(['title^2', 'body'], 'search'),
            ElasticFilter::trashed(),
        ];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        return [
            'created_at',
            'title',
            ElasticSort::field('views_count', 'views'),
            ElasticSort::score('relevance'),
        ];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        return [
            'author',
            'comments',
            'commentsCount',
            ElasticInclude::callback('recentComments', function ($builder) {
                $builder->with(['comments' => fn($q) => $q->latest()->limit(5)]);
            }),
        ];
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        return ['id', 'title', 'status', 'body', 'created_at', 'author.id', 'author.name'];
    }

    public function appends(QueryWizardInterface $wizard): array
    {
        return ['excerpt', 'reading_time'];
    }

    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        return ['-created_at'];
    }

    public function defaultIncludes(QueryWizardInterface $wizard): array
    {
        return ['author'];
    }

    public function defaultFields(QueryWizardInterface $wizard): array
    {
        return ['id', 'title', 'status', 'created_at'];
    }

    public function defaultAppends(QueryWizardInterface $wizard): array
    {
        return ['excerpt'];
    }

    /**
     * Default filter values applied when not present in request.
     * Keys are filter names (or aliases), values are default values.
     */
    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return [
            'status' => 'published',
        ];
    }
}
```

### Using Schemas

```php
// Create wizard from schema class
$posts = ElasticQueryWizard::forSchema(PostSchema::class)
    ->build()
    ->execute()
    ->models();

// Or with schema instance
$schema = new PostSchema();
$posts = ElasticQueryWizard::forSchema($schema)
    ->build()
    ->execute()
    ->models();
```

### Combining Schemas with Overrides

Use a schema as a base and override specific settings with `disallowed*()` methods:

```php
// Admin endpoint: full access
ElasticQueryWizard::forSchema(PostSchema::class)
    ->build()
    ->execute();

// Public endpoint: restricted access
ElasticQueryWizard::forSchema(PostSchema::class)
    ->disallowedFilters('status', 'trashed')     // Remove sensitive filters
    ->disallowedIncludes('comments')             // Remove heavy includes
    ->disallowedFields('body')                   // Hide full content
    ->build()
    ->execute();

// Add extra filters not in schema (rare case - usually use disallowed* instead)
$schema = app(PostSchema::class);
$wizard = ElasticQueryWizard::forSchema($schema);
$wizard->allowedFilters(
    ...$schema->filters($wizard),
    ElasticFilter::term('featured'),             // Additional filter
)
    ->build()
    ->execute();
```

### Wildcard Support in disallowed*() Methods

All `disallowed*()` methods support wildcards:

| Pattern | Meaning | Example |
|---------|---------|---------|
| `'*'` | Block everything | `disallowedFields('*')` |
| `'relation.*'` | Block direct children only | `disallowedFields('author.*')` blocks `author.email` but not `author.posts.id` |
| `'relation'` | Block relation and all descendants | `disallowedFields('author')` blocks `author`, `author.id`, `author.posts.id` |

```php
ElasticQueryWizard::forSchema(PostSchema::class)
    ->disallowedFields('author.*')      // Block author fields, keep author relation
    ->disallowedIncludes('comments')    // Block comments and all nested
    ->build();
```

### Context-Aware Schemas

Schema methods receive the wizard instance, enabling conditional logic based on wizard type or runtime context:

```php
use Jackardios\QueryWizard\ModelQueryWizard;

class PostSchema extends ResourceSchema
{
    public function filters(QueryWizardInterface $wizard): array
    {
        // No filters for ModelQueryWizard (already-loaded models)
        if ($wizard instanceof ModelQueryWizard) {
            return [];
        }

        $filters = [
            'status',
            ElasticFilter::match('title'),
        ];

        // Add admin-only filters
        if (auth()->user()?->isAdmin()) {
            $filters[] = ElasticFilter::trashed();
            $filters[] = ElasticFilter::term('author_id');
        }

        return $filters;
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        $includes = ['author'];

        // Heavy includes only for authenticated users
        if (auth()->check()) {
            $includes[] = 'comments';
            $includes[] = 'commentsCount';
        }

        return $includes;
    }

    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        // Admins see all posts, others see only published
        if (auth()->user()?->isAdmin()) {
            return [];
        }

        return ['status' => 'published'];
    }
}
```

### Schema Methods Reference

| Method | Description |
|--------|-------------|
| `model()` | **Required.** Model class name |
| `type()` | Resource type for `?fields[type]=...` (default: camelCase of model) |
| `filters($wizard)` | Allowed filters |
| `sorts($wizard)` | Allowed sorts |
| `includes($wizard)` | Allowed includes |
| `fields($wizard)` | Allowed fields for sparse fieldsets |
| `appends($wizard)` | Allowed computed attributes |
| `defaultSorts($wizard)` | Default sorts when none requested |
| `defaultIncludes($wizard)` | Default includes when `?include` absent |
| `defaultFields($wizard)` | Default fields when `?fields` absent |
| `defaultAppends($wizard)` | Default appends |
| `defaultFilters($wizard)` | Default filter values (associative array) |

---

## Fields and Appends

### Allowed Fields

Control which fields can be requested via the `fields` parameter:

```php
ElasticQueryWizard::for(Post::class)
    ->allowedFields(['id', 'title', 'status', 'body', 'created_at'])
    ->build();
```

```
GET /posts?fields[post]=id,title,status
```

### Allowed Appends

Control which Eloquent accessors can be appended:

```php
ElasticQueryWizard::for(Post::class)
    ->allowedAppends(['excerpt', 'reading_time', 'author_name'])
    ->build();
```

```
GET /posts?append=excerpt,reading_time
```

---

## Creating Custom Filters

### Extending AbstractElasticFilter

Custom filters should implement the `buildQuery()` method which returns the Elasticsearch query. The query is automatically added to the appropriate bool clause based on the filter's effective clause (configurable via `inFilter()`, `inMust()`, etc.).

```php
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

class CustomFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'custom';
    }

    /**
     * Override default clause if needed (default is FILTER).
     */
    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    /**
     * Build the Elasticsearch query.
     * Return null to skip the filter.
     * You can also return raw array query fragments for low-level DSL cases.
     */
    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        if (empty($value)) {
            return null;
        }

        // Your custom filter logic
        $query = Query::bool()
            ->should(Query::term($this->property, $value))
            ->should(Query::match($this->property . '_text', $value))
            ->minimumShouldMatch(1);

        return $this->applyParametersOnQuery($query);
    }
}
```

### Using Custom Filters

```php
ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        CustomFilter::make('product_code'),
    ])
    ->build();
```

---

## Creating Custom Sorts

### Extending AbstractSort

```php
use Jackardios\QueryWizard\Sorts\AbstractSort;
use Jackardios\EsScoutDriver\Sort\Sort;

class PopularitySort extends AbstractSort
{
    protected float $viewsWeight;
    protected float $likesWeight;

    protected function __construct(
        string $property,
        float $viewsWeight = 1.0,
        float $likesWeight = 2.0,
        ?string $alias = null
    ) {
        parent::__construct($property, $alias);
        $this->viewsWeight = $viewsWeight;
        $this->likesWeight = $likesWeight;
    }

    public static function make(
        string $property,
        float $viewsWeight = 1.0,
        float $likesWeight = 2.0,
        ?string $alias = null
    ): static {
        return new static($property, $viewsWeight, $likesWeight, $alias);
    }

    public function getType(): string
    {
        return 'popularity';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        $script = [
            'source' => "doc['views'].value * params.vw + doc['likes'].value * params.lw",
            'params' => [
                'vw' => $this->viewsWeight,
                'lw' => $this->likesWeight,
            ],
        ];

        $sort = Sort::script($script, 'number')->order($direction);
        $subject->sort($sort);

        return $subject;
    }
}
```

### Using Custom Sorts

```php
ElasticQueryWizard::for(Post::class)
    ->allowedSorts([
        PopularitySort::make('popularity', 1.0, 3.0, 'popular'),
    ])
    ->build();
```

```
GET /posts?sort=-popular
```

---

## Creating Custom Includes

### Extending AbstractElasticInclude

For includes that need access to Elasticsearch results:

```php
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Illuminate\Database\Eloquent\Builder;

class HighlightedInclude extends AbstractElasticInclude
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'highlighted';
    }

    public function handleEloquent(Builder $eloquentBuilder): void
    {
        $searchResult = $this->getSearchResult();

        if ($searchResult) {
            // Access highlights from Elasticsearch results
            $highlights = $searchResult->highlights();

            // Store for later use
            // You can attach this data to models in a collection callback
        }
    }
}
```

### Using CallbackInclude for Simple Cases

For simpler cases, use the callback include:

```php
use Jackardios\ElasticQueryWizard\ElasticInclude;

ElasticQueryWizard::for(Post::class)
    ->allowedIncludes([
        ElasticInclude::callback('recentActivity', function (Builder $builder) {
            $builder->with(['activities' => function ($query) {
                $query->latest()->limit(10);
            }]);
        }),
    ])
    ->build();
```

---

## Execution Methods

After building the wizard, you have several execution options:

```php
$wizard = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([...])
    ->build();

// Execute and get SearchResult
$searchResult = $wizard->execute();

// Get models
$models = $searchResult->models();

// Get total count
$total = $searchResult->total;

// Get raw hits
$hits = $searchResult->hits();

// Get aggregations
$aggregations = $searchResult->aggregations();

// Paginate
$paginated = $wizard->paginate(15);
```

---

## Method Chaining

The wizard supports method chaining with the underlying SearchBuilder:

```php
$results = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
    ])
    ->build()
    ->highlight('title')           // SearchBuilder method
    ->highlight('body')            // SearchBuilder method
    ->size(50)                     // SearchBuilder method
    ->from(0)                      // SearchBuilder method
    ->execute()
    ->models();
```

> **Note:** After `build()`, `modifyQuery()` and `modifyModels()` are locked and will throw a `LogicException`. Register these callbacks before build.
> **Note:** Once you call SearchBuilder methods after `build()`, you cannot modify wizard configuration (allowedFilters, allowedSorts, etc.) anymore.
