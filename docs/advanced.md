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
> use Illuminate\Database\Eloquent\Builder;
> use Illuminate\Database\Eloquent\Collection;
> ```

## Table of Contents

- [Resource Schemas](#resource-schemas)
- [Fields and Appends](#fields-and-appends)
- [Declarative SearchBuilder Methods](#declarative-searchbuilder-methods)
- [When a Change Runs](#when-a-change-runs)
- [Reading the Configuration](#reading-the-configuration)
- [DSL Proxies](#dsl-proxies)
- [Custom Aggregations](#custom-aggregations)
- [Working with Bool Query](#working-with-bool-query)
- [Modify Query Callbacks](#modify-query-callbacks)
- [Modify Models Callbacks](#modify-models-callbacks)
- [Accessing the SearchBuilder](#accessing-the-searchbuilder)
- [Creating Custom Filters](#creating-custom-filters)
- [Creating Custom Sorts](#creating-custom-sorts)
- [Creating Custom Includes](#creating-custom-includes)
- [Execution Methods](#execution-methods)
- [Method Chaining](#method-chaining)
- [Troubleshooting](#troubleshooting)

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
            ElasticFilter::multiMatch('search', ['title^2', 'body']),
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
     * Keys are filter names (or aliases), values are default values. A filter
     * inside a group is keyed by its own name, not the group's.
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
    ->tapSearchBuilder(fn ($builder) => $builder->filter(ElasticQuery::term('status', 'published')))
    ->disallowedFilters('status', 'trashed')     // Remove sensitive filters
    ->disallowedIncludes('comments', 'commentsCount') // Remove heavy includes; a count include has its own name
    ->disallowedFields('body')                   // Hide full content
    ->build()
    ->execute();

// Add extra filters not in schema (rare case - usually use disallowed* instead)
$schema = app(PostSchema::class);
$wizard = ElasticQueryWizard::forSchema($schema);
$wizard->allowedFilters([
    ...$schema->filters($wizard),
    ElasticFilter::term('featured'),             // Additional filter
])
    ->build()
    ->execute();
```

> **Warning:** `disallowedFilters()` also drops the schema's `defaultFilters()` entry for that filter. The
> `['status' => 'published']` default above stops restricting the results once `status` is disallowed, which is why the
> public endpoint adds the condition to the search itself with `tapSearchBuilder()`. Put a condition that must always
> hold on the search, not in a filter default.

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
    ->disallowedIncludes('comments')    // Block comments and its nested includes, not commentsCount
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

Sparse fieldsets, appends and includes shape the Eloquent models loaded for the hits (`models()`, `withModels()` on the
paginator). The documents (`documents()`, `withDocuments()`) hold each hit's full `_source`; limit it with the search
builder's `source()` when the documents are returned to the client.

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

## Declarative SearchBuilder Methods

`ElasticQueryWizard` exposes most useful `SearchBuilder` operations directly.
Called before `build()`, these methods are declarative: the wizard queues them and applies them on every build, so
they survive a later configuration change. Called after `build()`, they change the built search at once, and the
result is not the same: see [When a Change Runs](#when-a-change-runs).

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

`when()` and `unless()` are declarative as well: their callback receives the search builder when the wizard builds. Any
other `SearchBuilder` method, such as `count()` or `getBoolQuery()`, and any `SearchBuilder` macro builds the wizard
first and runs on the built search; the wizard is returned in place of the builder. A macro is not queued even when
it returns the builder: `$wizard->myMacro()->allowedFilters(…)` throws a `LogicException`, since the macro has changed
the built search. Configure the wizard first, or call the macro on the builder inside `tapSearchBuilder()`.

```php
ElasticQueryWizard::for(Post::class)
    ->when($request->boolean('featured'), fn ($builder) => $builder->filter(ElasticQuery::term('featured', true)))
    ->allowedFilters([ElasticFilter::term('status')]);
```

You can also use `tapSearchBuilder()` to apply arbitrary mutations:

```php
ElasticQueryWizard::for(Post::class)
    ->tapSearchBuilder(function ($builder) {
        $builder->trackTotalHits(true);
        $builder->timeout('2s');
    });
```

---

## When a Change Runs

A build applies, in this order: the `tap()` callbacks, the queued search builder calls and `tapSearchBuilder()`
callbacks, the request's filters, its sorts, the `tapBuiltSearch()` callbacks, and last the callbacks that load the
models with their includes, fields and appends.

| Call | Before `build()` | After `build()` |
|------|------------------|-----------------|
| A fluent search builder method on the wizard (`sortRaw()`, `must()`, `size()`, …), `when()`, `unless()`, `tapSearchBuilder()` | Queued: runs at the start of every build, **before** the filters and sorts | Runs once, at once, on the built search, **after** the filters and sorts; a later configuration change throws a `LogicException` |
| `tap()` (from `laravel-query-wizard`) | Queued: runs at the start of every build | Queued: the next `build()` rebuilds the search |
| `tapBuiltSearch()` | Queued: runs at the end of every build, **after** the filters and sorts | Queued: the next `build()` rebuilds the search |
| `modifyQuery()`, `modifyModels()` | Registered for every build | `LogicException` |

So the same call gives another search on each side of the build. With `?sort=-name` and `allowedSorts('name')`:

```php
$wizard->sortRaw([['_score' => 'desc']]);   // before build(): sort is [_score desc, name desc]

$wizard->build();
$wizard->sortRaw([['_score' => 'desc']]);   // after build(): sort is [_score desc], the client's sort is replaced
```

`boolQuery()`, `getSort()`, `count()` and every other method that returns a value build the wizard, so a call placed
above such a line moves a fluent call below it to the "after" column without an error. Keep to one side: make the
calls before anything builds the wizard, or put the change in a callback.

`tapBuiltSearch()` is the callback for a change that needs the filtered query, on every build. It receives the search
builder with the request's filters and sorts on it:

```php
use Jackardios\EsScoutDriver\Search\SearchBuilder;

ElasticQueryWizard::for(Post::class)
    ->allowedFilters([ElasticFilter::term('status')])
    ->tapBuiltSearch(function (SearchBuilder $builder) {
        // Wrap what the request filtered in a function_score
        $builder->query(
            ElasticQuery::functionScore(clone $builder->boolQuery())
                ->addFunction(['field_value_factor' => ['field' => 'views']])
        )->clearBoolQuery();
    });
```

`clearBoolQuery()` is part of the wrapping: the search builder sends its bool query together with what `query()`
sets, so without it the filters apply a second time beside the `function_score`.

Inside any of these callbacks, and inside a callback filter or sort, use the builder the callback receives. On the
wizard only `boolQuery()` works while it builds: it returns the bool query of the search being built, the same object
as `$builder->boolQuery()`. In a `tapSearchBuilder()` callback that bool query does not hold the request's filters
yet; in a `tapBuiltSearch()` callback it does. Other search builder methods called on the wizard from a callback,
`build()`, `paginate()` and `applyPostProcessingTo()`, and the methods that register callbacks, throw a
`LogicException`.

A clone carries the callbacks of the wizard it was cloned from. A callback that captured `$wizard` still changes that
wizard when the clone builds, not the clone: one more reason to use the builder the callback receives.

Do not call `clearQueryModifiers()`, `clearModelModifiers()` or `clearAll()` on a built wizard or on the builder
`build()` returned: they remove the callbacks that apply includes, fields and appends to the models.

---

## Reading the Configuration

The wizard reports what a request may use, without building:

```php
$wizard = ElasticQueryWizard::forSchema(PostSchema::class)->disallowedFilters('trashed');

$wizard->getAllowedFilters();        // ['status' => TermFilter, 'search' => BoolGroup, …] by public name
$wizard->getAllowedLeafFilters();    // the same with every group replaced by its leaves, by request key
$wizard->getAllowedSorts();          // ['created_at' => FieldSort, …]
$wizard->getAllowedIncludes();       // by include name
$wizard->getAllowedFields();         // list of field names
$wizard->getAllowedAppends();        // list of append names
$wizard->getRequestedFilterNames();  // the filter keys of the current request, allowed or not
```

All but `getAllowedLeafFilters()` come from `laravel-query-wizard`. `getAllowedFilters()` lists a group under the
group's name, which is not a request key; `getAllowedLeafFilters()` lists what `?filter[…]` may name: the leaves of
every group, without those `disallowedFilters()` removes, and without a root filter whose key a group leaf of the same
name takes. `getRequestedFilterNames()` names leaves too. The filters, sorts and includes returned are copies:
changing one does not change the wizard. None of them can be called from a schema method, which is describing that
configuration.

`ElasticQueryWizard::for($modelClass, $parameters)` takes a `QueryParametersManager` as its second argument, to read
another request than the current one; `laravel-query-wizard`'s own `for()` refuses a second argument.

---

## DSL Proxies

To keep API style consistent inside this package, use:

- `ElasticQuery` as a proxy to `Jackardios\EsScoutDriver\Support\Query`
- `ElasticAggregation` as a proxy to `Jackardios\EsScoutDriver\Aggregations\Agg`

```php
ElasticQuery::multiMatch(['title^2', 'body'], 'laravel search');
ElasticAggregation::stats('price');
```

Both proxies forward every factory of those classes, and their macros. Each factory is declared with its return type,
so IDEs and static analysis see the query or aggregation it creates.

---

## Custom Aggregations

Add Elasticsearch aggregations to collect analytics alongside search results:

```php
$search = ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        ElasticFilter::term('category'),
        ElasticFilter::range('price'),
    ])
    ->aggregate('categories', ElasticAggregation::terms('category')->size(20))
    ->aggregate('price_stats', ElasticAggregation::stats('price'))
    ->aggregate('price_histogram', ElasticAggregation::histogram('price', 100))
    ->build();

$results = $search->execute();

// Get aggregation results
$aggregations = $results->aggregations();
$categories = $aggregations['categories']['buckets'];
$priceStats = $aggregations['price_stats'];
```

### Nested Aggregations

```php
$search->aggregate(
    'categories',
    ElasticAggregation::terms('category')
        ->agg('avg_price', ElasticAggregation::avg('price'))
        ->agg('brands', ElasticAggregation::terms('brand')->size(5))
);
```

---

## Working with Bool Query

Add clauses with the wizard's `must()`, `filter()`, `should()` and `mustNot()`. Called before the build they are
queued, so the configuration stays open and the clauses survive a rebuild:

```php
$wizard = ElasticQueryWizard::for(Post::class)
    ->must(ElasticQuery::match('title', 'search term'))
    ->mustNot(ElasticQuery::term('is_hidden', true))
    ->allowedFilters([ElasticFilter::term('status')]);
```

For what these methods do not cover, such as `minimum_should_match`, access the root bool query of the built search:

```php
$search = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
    ])
    ->build();

// Access the bool query directly
$boolQuery = $search->boolQuery();

// Add must clause
$boolQuery->addMust(ElasticQuery::match('title', 'search term'));

// Add should clause with minimum_should_match
$boolQuery->addShould(ElasticQuery::term('is_featured', true));
$boolQuery->addShould(ElasticQuery::range('views')->gte(1000));
$boolQuery->minimumShouldMatch(1);

// Add must_not clause
$boolQuery->addMustNot(ElasticQuery::term('is_hidden', true));
```

> **Note:** The wizard's `boolQuery()` and `getBoolQuery()` build the wizard first, like other `SearchBuilder` methods
> that return a value, and return the built search's bool query. A change made there lives on the built search, which a
> rebuild replaces, so `boolQuery()` locks the configuration: a later change throws a `LogicException`.
> `getBoolQuery()` locks it only when it returns a bool query; for a search without clauses it returns `null` and
> locks nothing. Use `tapSearchBuilder()` or `tapBuiltSearch()` for a change that survives rebuilds
> ([When a Change Runs](#when-a-change-runs)); inside their callbacks `$wizard->boolQuery()` returns the bool query
> being built and locks nothing.

---

## Modify Query Callbacks

Add callbacks that modify the Eloquent query before loading models.
This API is consistent with `SearchBuilder::modifyQuery()` from `es-scout-driver`:
the second callback argument receives raw Elasticsearch response array.
Register callbacks before calling `build()`. The callbacks, includes, sparse fieldsets and appends apply to the
wizard's model: when the search also covers another index through `join()`, that index's models load without them.

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
This API is consistent with `SearchBuilder::modifyModels()` from `es-scout-driver`.
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

## Accessing the SearchBuilder

`build()` returns the underlying `SearchBuilder`, for any low-level functionality not covered by helper methods:

```php
$search = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
    ])
    ->build();

// Add custom query
$search->must(ElasticQuery::matchPhrase('content', 'exact phrase'));

// Execute and get results
$results = $search->execute();
```

Before the build, `$wizard->getSubject()` returns the same `SearchBuilder` without applying the request.

---

## Creating Custom Filters

### Extending AbstractElasticFilter

Custom filters should implement the `buildQuery()` method which returns the Elasticsearch query. The query is automatically added to the appropriate bool clause based on the filter's effective clause (configurable via `inFilter()`, `inMust()`, etc.).

```php
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Support\FilterValueParser;

class CustomFilter extends AbstractElasticFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Override default clause if needed (default is BoolClause::Filter).
     */
    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    /**
     * Build the Elasticsearch query.
     * Return null to skip the filter (not an empty array, which the driver refuses).
     * You can also return raw array query fragments for low-level DSL cases.
     */
    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        if (FilterValueParser::isBlank($value)) {
            return null;
        }

        // `a,b` arrives as a list; a 400 names the filter and the value
        if (! is_string($value) && ! is_int($value)) {
            throw InvalidFilterValue::make($value, $this, 'Expected one product code.');
        }

        // Your custom filter logic
        return Query::bool()
            ->should(Query::term($this->property, $value))
            ->should(Query::match($this->property . '_text', $value))
            ->minimumShouldMatch(1);
    }
}
```

Read values with `laravel-query-wizard`'s `Support\FilterValueParser` (`isBlank()`, `number()`, `boolean()`,
`isoDate()`, …): it returns null for a blank value and throws the 400 `InvalidFilterValue` for one it cannot read.
`empty()` would also drop `0`.

The hooks a custom filter may override or call are marked `@api`: `buildQuery()`, `getDefaultClause()`,
`validateValueShape()` and `validateScalarOrBlankValueShape()`. The `Concerns` traits the built-in filters use
(`HasParameters`, `LimitsValueLength`, …) are `@internal`; set the query's options in `buildQuery()` instead. A custom
group extends `AbstractElasticGroup` and builds its query with `applyChildrenToQuery()`.

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
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Sorts\AbstractSort;

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

    public function apply(mixed $subject, SortDirection $direction): mixed
    {
        $script = [
            'source' => "doc['views'].value * params.vw + doc['likes'].value * params.lw",
            'params' => [
                'vw' => $this->viewsWeight,
                'lw' => $this->likesWeight,
            ],
        ];

        $sort = Sort::script($script, 'number')->order($direction->value);
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
use Jackardios\EsScoutDriver\Search\Hit;
use Illuminate\Database\Eloquent\Builder;

class HighlightsInclude extends AbstractElasticInclude
{
    /** @var array<string, array<string, mixed>> Highlights by document id */
    public array $highlightsById = [];

    public static function make(string $relation, ?string $alias = null): static
    {
        return new static($relation, $alias);
    }

    public function handleEloquent(Builder $eloquentBuilder): void
    {
        $this->highlightsById = $this->getSearchResult()
            ?->hits()
            ->filter(fn (Hit $hit) => $hit->highlight !== [])
            ->mapWithKeys(fn (Hit $hit) => [$hit->documentId => $hit->highlight])
            ->all() ?? [];
    }
}
```

`handleEloquent()` runs before the models are loaded, and `getSearchResult()` holds the raw response only: its hits
carry the document id, source, score and highlight, while `$hit->model()` is always `null` there. An include has no
step after the models are loaded, so attach what it collected in a `modifyModels()` callback on the wizard:

```php
$highlights = HighlightsInclude::make('highlights');

ElasticQueryWizard::for(Post::class)
    ->highlight('title')
    ->allowedIncludes([$highlights])
    ->modifyModels(fn (Collection $posts) => $posts->each(
        fn (Post $post) => $post->setAttribute('highlights', $highlights->highlightsById[$post->getScoutKey()] ?? [])
    ));
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

The callback changes the given builder in place; a builder it returns is ignored.

---

## Execution Methods

`build()` returns the `SearchBuilder`, which has several execution options:

```php
$search = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([...])
    ->build();

// Execute and get SearchResult
$searchResult = $search->execute();

// Get models
$models = $searchResult->models();

// Get total count
$total = $searchResult->total;

// Get raw hits
$hits = $searchResult->hits();

// Get aggregations
$aggregations = $searchResult->aggregations();

// Paginate
$paginated = $search->paginate(15);
```

### Pagination

Paginate through the wizard to have the page checked against the result window:

```php
$paginator = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([...])
    ->paginate(15);        // reads ?page=; call withModels() or withDocuments() on the paginator
```

Elasticsearch refuses a search whose `from + size` exceeds the index's `max_result_window` (10000 by default) with an
error that reaches the client as a 500. The wizard's `paginate()` answers such a page with 400
`MaxResultWindowExceeded` (error code `max_result_window_exceeded`, `MaxResultWindowExceeded::ERROR_CODE`) before
searching. A page size or a page number below 1, which the search builder refuses with an `InvalidArgumentException`,
is a 400 `InvalidPagination` (error code `invalid_pagination`), so a page size taken from the request needs only an
upper bound from the application. The package ships no config file; to set another limit, or `null` to turn the check off, create
`config/elastic-query-wizard.php` in the application:

```php
return [
    'max_result_window' => 50000, // match the index setting
];
```

The value is a positive integer, a string of digits such as `env()` returns, or `null`; anything else throws an
`InvalidArgumentException` when the wizard paginates. The same file takes `max_text_length`, the default length limit
of the text filters ([Filters: Security](filters.md#security)).

The wizard's `paginate()` takes 15 results per page by default, like Eloquent; `paginate()` on the search builder takes
10. `paginate()` on the search builder that `build()` returns does not check the window. For results deeper than the
window, use `searchAfter()` or a point in time.

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
> Registering a callback or calling a SearchBuilder method on the wizard while it builds (from a `tap()` callback,
> a filter or a schema method) throws a `LogicException` as well; use the builder the callback receives. The wizard's
> `boolQuery()` is the exception: it returns the bool query being built.
> **Note:** Once you call SearchBuilder methods on the wizard after `build()`, changing its configuration (allowedFilters, allowedSorts, etc.) throws a `LogicException`.
> Calls on the builder that `build()` returned are not tracked: a later configuration change rebuilds from a fresh builder and silently drops them. To keep a change across rebuilds, call the method on the wizard before `build()` or use `tapSearchBuilder()` or `tapBuiltSearch()`; [When a Change Runs](#when-a-change-runs) has the order.

---

## Troubleshooting

### Filter Not Working

**Problem:** Filter parameter is ignored or has no effect.

**Checklist:**
1. **Filter not registered** — Ensure the filter is in `allowedFilters()`:
   ```php
   ->allowedFilters([
       ElasticFilter::term('status'),  // Must be listed
   ])
   ```

2. **Field name mismatch** — Verify the field name matches your Elasticsearch mapping:
   ```php
   // Wrong: field doesn't exist in ES mapping
   ElasticFilter::term('Status')  // ES fields are case-sensitive

   // Correct: use exact field name from mapping
   ElasticFilter::term('status')
   ```

3. **Wrong filter type for field** — Use the correct filter for your field type:
   ```php
   // Wrong: term on analyzed text field returns no results
   ElasticFilter::term('title')

   // Correct: use match for text fields
   ElasticFilter::match('title')

   // Or use .keyword subfield for exact match on text
   ElasticFilter::term('title.keyword')
   ```

4. **Empty value** — Most filters skip empty values. Check your request:
   ```
   ?filter[status]=        # Empty string — filter skipped
   ?filter[status]=active  # Has value — filter applied
   ```

### Sort Not Applied

**Problem:** Sort parameter is ignored.

**Checklist:**
1. **Sort not registered** — Ensure the sort is in `allowedSorts()`:
   ```php
   ->allowedSorts([
       ElasticSort::field('created_at'),  // Must be listed
   ])
   ```

2. **Text field without keyword** — Text fields can't be sorted directly:
   ```php
   // Wrong: text fields are analyzed, not sortable
   ElasticSort::field('title')

   // Correct: use .keyword subfield
   ElasticSort::field('title.keyword', 'title')
   ```

3. **Default sort overridden** — Request sort takes precedence:
   ```php
   ->defaultSorts('-created_at')  // Applied only when ?sort is absent
   ```

   An empty `?sort=` is not absent: it returns 400 (`InvalidSortQuery`).

### Include Not Loading

**Problem:** Related data is missing from results.

**Checklist:**
1. **Include not registered** — Ensure the include is in `allowedIncludes()`:
   ```php
   ->allowedIncludes(['author', 'comments'])
   ```

2. **Relation not defined** — Verify the Eloquent relation exists on your model:
   ```php
   // Model must have this relation
   public function author(): BelongsTo
   {
       return $this->belongsTo(User::class);
   }
   ```

3. **Count include syntax** — Use `Relation` + `Count` suffix:
   ```
   ?include=commentsCount  # Loads count, not relation
   ```

### Random Sort Repeats or Skips Documents Across Pages

```php
// Without a seed every request shuffles anew; a seed keeps one order per session
ElasticSort::random('random')->seed($request->session()->getId())
```

### Elasticsearch 9.x Specific Errors

**"force_source not supported"**
```php
// Remove force_source from highlight options
->tapSearchBuilder(function ($builder) {
    $builder->highlight('title');  // Don't use force_source: true
})
```

### Query Returning No Results

**Problem:** Query should return results but returns empty.

**Checklist:**
1. **Check bool clause placement** — A scoring filter in the `filter` clause matches but adds no score, so the best
   matches are not first:
   ```php
   // Matches, ordered without relevance
   ElasticFilter::match('title')->inFilter()

   // Better: let match use default must clause
   ElasticFilter::match('title')
   ```

2. **Nested field not in nested query** — Use nested filter for nested fields:
   ```php
   // Wrong: treating nested field as regular field
   ElasticFilter::term('comments.author')

   // Correct: use nested filter
   ElasticFilter::nested('author', 'comments')
   ```

3. **Index not synced** — Ensure your Elasticsearch index is up to date:
   ```bash
   php artisan scout:flush "App\\Models\\Post"
   php artisan scout:import "App\\Models\\Post"
   ```

### Performance Issues

**Slow queries:**
- Use `inFilter()` for exact filters (cached, no scoring)
- Avoid `wildcard` with leading `*` on large indices
- Use `from`/`size` for pagination, not large `size` values
- Add `trackTotalHits(false)` if you don't need exact counts

**Memory issues:**
- Use `paginate()` instead of loading all results
- Limit aggregation `size` parameter
- Use `source()` to limit returned fields
