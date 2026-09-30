# Migration Guide: v2 → v3

This guide covers migrating from `jackardios/elastic-query-wizard` v2 to v3.

## Dependencies

**v2:**
```json
"jackardios/elastic-scout-driver-plus": "^4.0.0",
"jackardios/laravel-query-wizard": "^2.0.2",
"laravel/framework": "^v10.0"
```

**v3:**
```json
"php": "^8.2",
"jackardios/es-scout-driver": "^1.0.0-rc.1",
"jackardios/laravel-query-wizard": "^3.0.0-rc.3",
"laravel/framework": "^12.61.1 || ^13.12.0"
```

> **Important:** The underlying ES driver changed from `elastic-scout-driver-plus` to `es-scout-driver`. This is a completely different package with different APIs.

`ElasticQueryWizard::for()` takes the model class (`ElasticQueryWizard::for(Post::class)`); a model instance is a
`TypeError`. The configuration rules of `laravel-query-wizard` 3.0 apply as well: `disallowed*()` calls add up, defaults
apply without being allowed, and duplicate public names throw. See its UPGRADE.md.

---

## Removed/Renamed Classes

| v2 Class | v3 Replacement |
|----------|----------------|
| `ElasticRootBoolQuery` | Removed. Use `$builder->must()`, `filter()`, etc. directly |
| `ElasticFilter` (abstract) | `AbstractElasticFilter` |
| `ElasticSort` (abstract) | `AbstractElasticSort` |
| `ElasticInclude` (abstract) | `AbstractElasticInclude` |
| `Filters\CallbackFilter` | Use `ElasticFilter::callback()` (from laravel-query-wizard) |
| `Sorts\CallbackSort` | Use `ElasticSort::callback()` (from laravel-query-wizard) |
| `Includes\CallbackInclude` | Use `ElasticInclude::callback()` (from laravel-query-wizard) |
| `Includes\RelationshipInclude` | Use `ElasticInclude::relationship()` (from laravel-query-wizard) |
| `Includes\CountInclude` | Use `ElasticInclude::count()` (from laravel-query-wizard) |

**New factory classes in v3:**
- `ElasticFilter` — static factory for all filter types
- `ElasticSort` — static factory for all sort types
- `ElasticInclude` — static factory for all include types
- `ElasticQuery` — DSL proxy for ES queries
- `ElasticAggregation` — DSL proxy for ES aggregations

---

## Namespace Changes

| v2 | v3 |
|----|-----|
| `Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder` | `Jackardios\EsScoutDriver\Search\SearchBuilder` |
| `Elastic\Adapter\Search\SearchResult` | `Jackardios\EsScoutDriver\Search\SearchResult` |
| `Elastic\ScoutDriverPlus\Support\Query` | `Jackardios\EsScoutDriver\Support\Query` |

---

## Quick Reference: Method Renames

| v2 | v3 |
|----|-----|
| `setAllowedFilters()` | `allowedFilters()` |
| `setAllowedSorts()` | `allowedSorts()` |
| `setAllowedIncludes()` | `allowedIncludes()` |
| `setAllowedFields()` | `allowedFields()` |
| `setAllowedAppends()` | `allowedAppends()` |
| `setDefaultSorts()` | `defaultSorts()` |
| `getPropertyName()` | `getProperty()`, or `$this->property` (protected) inside the filter |
| `getInclude()` | `getRelation()`, or `$this->relation` (protected) inside the include |
| `addEloquentQueryCallback()` | `modifyQuery()` |
| `addEloquentCollectionCallback()` | `modifyModels()` |
| `getRootBoolQuery()` | `boolQuery()` or use `$builder` directly |
| `->build()->get()` | `->build()->execute()` |
| `build()` returned the wizard | `build()` returns the `SearchBuilder`; keep the wizard in its own variable |

---

## Query DSL API Changes (es-scout-driver)

The Query DSL API changed from builder pattern to static factories:

**v2 (elastic-scout-driver-plus):**
```php
Query::term()->field('status')->value('active')
Query::terms()->field('status')->values(['active', 'pending'])
Query::match()->field('title')->query('search text')
Query::range()->field('price')->gte(100)->lte(500)
```

**v3 (es-scout-driver):**
```php
Query::term('status', 'active')
Query::terms('status', ['active', 'pending'])
Query::match('title', 'search text')
Query::range('price')->gte(100)->lte(500)
```

---

## Filter/Sort/Include Instantiation

### v2: Direct constructors
```php
new TermFilter('status');
new MatchFilter('title');
new FieldSort('created_at');
new RelationshipInclude('author');
```

### v3: Factory methods (constructors are protected)
```php
// Option 1: Use concrete class ::make()
TermFilter::make('status');
MatchFilter::make('title');
FieldSort::make('created_at');
RelationshipInclude::make('author');

// Option 2: Use factory classes (recommended)
ElasticFilter::term('status');
ElasticFilter::match('title');
ElasticSort::field('created_at');
ElasticInclude::relationship('author');
```

---

## Complete Usage Example

### v2
```php
$results = ElasticQueryWizard::for(Product::class)
    ->setAllowedFilters([
        new TermFilter('status'),
        new MatchFilter('title'),
        new RangeFilter('price'),
    ])
    ->setAllowedSorts([
        new FieldSort('created_at'),
    ])
    ->setAllowedIncludes([
        new RelationshipInclude('category'),
        new CountInclude('comments'),
    ])
    ->setDefaultSorts('-created_at')
    ->build()
    ->get();
```

### v3
```php
$results = ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
        ElasticFilter::match('title'),
        ElasticFilter::range('price'),
    ])
    ->allowedSorts([
        ElasticSort::field('created_at'),
    ])
    ->allowedIncludes([
        ElasticInclude::relationship('category'),
        ElasticInclude::count('comments'),
    ])
    ->defaultSorts('-created_at')
    ->build()
    ->execute();
```

---

## Custom Filters (Extending AbstractElasticFilter)

### v2
```php
use Jackardios\ElasticQueryWizard\ElasticFilter;

class CustomFilter extends ElasticFilter
{
    public function handle($queryWizard, SearchParametersBuilder $builder, $value): void
    {
        $rootBoolQuery = $queryWizard->getRootBoolQuery();
        $rootBoolQuery->must(/* ... */);
    }
}

// Usage
new CustomFilter('property_name');
```

### v3
```php
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class CustomFilter extends AbstractElasticFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        return is_string($value) ? Query::match($this->property, $value) : null;
    }
}

// Usage
CustomFilter::make('property_name');
```

**Key changes:**
- Base class: `ElasticFilter` → `AbstractElasticFilter`
- Class should be `final`
- Implement `buildQuery()` instead of `handle()`: return the query (or `null` to add nothing), and the wizard adds it
  to the filter's bool clause, so `inMust()`, `inShould()` and bool groups work with custom filters
- The default clause is `filter`; override `getDefaultClause()` to change it
- No more `getRootBoolQuery()`
- Access property via `$this->property` instead of `$this->getPropertyName()`
- Static `::make()` factory required

---

## Custom Sorts (Extending AbstractElasticSort)

### v2
```php
use Jackardios\ElasticQueryWizard\ElasticSort;

class CustomSort extends ElasticSort
{
    public function handle($queryWizard, SearchParametersBuilder $builder, string $direction): void
    {
        $builder->sort(/* ... */);
    }
}

// Usage
new CustomSort('property_name');
```

### v3
```php
use Jackardios\ElasticQueryWizard\Sorts\AbstractElasticSort;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;

final class CustomSort extends AbstractElasticSort
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function handle(SearchBuilder $builder, string $direction): void
    {
        $sort = Sort::field($this->property)->order($direction);
        $builder->sort($sort);
    }
}

// Usage
CustomSort::make('property_name');
```

---

## Custom Includes (Extending AbstractElasticInclude)

### v2
```php
use Jackardios\ElasticQueryWizard\ElasticInclude;

class CustomInclude extends ElasticInclude
{
    public function handle($queryWizard, Builder $eloquentBuilder): void
    {
        $eloquentBuilder->with(/* ... */);
    }
}

// Usage
new CustomInclude('relation_name');
```

### v3
```php
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Illuminate\Database\Eloquent\Builder;

final class CustomInclude extends AbstractElasticInclude
{
    public static function make(string $relation, ?string $alias = null): static
    {
        return new static($relation, $alias);
    }

    public function handleEloquent(Builder $eloquentBuilder): void
    {
        $relationName = $this->relation;  // Not getInclude()!
        $eloquentBuilder->with($relationName);
    }
}

// Usage
CustomInclude::make('relation_name');
```

**Key changes:**
- Base class: `ElasticInclude` → `AbstractElasticInclude`
- Method: `handle()` → `handleEloquent()`
- `$queryWizard` parameter removed
- Access relation via `$this->relation` instead of `$this->getInclude()`

---

## ElasticQueryWizard Changes

### Class Structure
- v2: `class ElasticQueryWizard extends AbstractQueryWizard`
- v3: `class ElasticQueryWizard extends BaseQueryWizard`

### Callback Methods

**v2:**
```php
$wizard->addEloquentQueryCallback(function(Builder $builder, SearchResult $result) {
    // modify query before loading models
});

$wizard->addEloquentCollectionCallback(function(Collection $collection) {
    return $collection->filter(...);
});
```

**v3:**
```php
$wizard->modifyQuery(function(Builder $builder, array $rawResult) {
    // modify query before loading models
});

$wizard->modifyModels(function(Collection $collection) {
    return $collection->filter(...);
});

// New: tap into SearchBuilder directly
$wizard->tapSearchBuilder(function(SearchBuilder $builder) {
    $builder->highlight('title');
});
```

The second argument of the `modifyQuery()` callback is the raw Elasticsearch response as an array; v2 passed a
`SearchResult`, so a callback that type-hints it throws a `TypeError`.

### Eloquent Filters

v2 accepted `EloquentFilter` instances in `allowedFilters()` and applied them to the Eloquent query that loads the
models. v3 takes Elasticsearch filters only: an Eloquent filter fails with a `TypeError` (a 500) on the first request
that uses it. Replace it with an `ElasticFilter` so the condition is part of the search and of its total.

### Accessing Bool Query

**v2:**
```php
$wizard->getRootBoolQuery()->must($query);
$wizard->getRootBoolQuery()->filter($query);
$wizard->getRootBoolQuery()->should($query);
$wizard->getRootBoolQuery()->mustNot($query);
```

**v3:**
```php
$wizard->tapSearchBuilder(fn (SearchBuilder $builder) => $builder->boolQuery()->must($query));
// Or once the configuration is final:
$wizard->boolQuery()->must($query);
// Or in filters, use $builder directly:
$builder->must($query);
$builder->filter($query);
$builder->should($query);
$builder->mustNot($query);
```

`tapSearchBuilder()` runs again on every build. A change made through `boolQuery()` stays on the built search, so a
configuration call after `build()` throws a `LogicException` instead of rebuilding without it.

### Sparse Fieldsets

Root and relation fieldsets follow `laravel-query-wizard`'s `EloquentQueryWizard`: an explicit empty root fieldset
(`?fields[post]=`) and a root fieldset whose fields were all dropped as invalid (with
`ignore_unknown.fields` on) hide every root attribute. Before, both returned every column. Without a
`fields` parameter for the resource the models keep every attribute, as before.

The primary key and the Scout key are always selected, and hidden unless requested. A root fieldset keeps the count and
exists includes, the selects added in `modifyQuery()` (hidden unless requested) and the keys of the eager loads registered
there. A relation fieldset runs after the eager-load constraint registered in `modifyQuery()` and keeps the related
model's `$with` and `$withCount`.

### Soft Deletes

The soft delete mode moved from the bool query to the search builder in `es-scout-driver` 1.0:

```php
// Before
$wizard->boolQuery()->withTrashed();

// After
$wizard->withTrashed();   // also onlyTrashed(), excludeTrashed()
```

### Request Handling (laravel-query-wizard v3)

The request is parsed by `laravel-query-wizard` v3, whose stricter rules apply to the elastic wizard too. The main ones
(see its [UPGRADE.md](https://github.com/Jackardios/laravel-query-wizard/blob/master/UPGRADE.md) for the full list):

- A filter value of the wrong shape (a list where a filter takes one value, a scalar for `dateRange`) → 400
  (`InvalidFilterQuery`).
- Blank values are absent: `null`, a whitespace-only string, `,` and a list of blanks add no condition, and with
  `filters.apply_default_on_null` the default applies to them.
- An empty `?sort=` (also `-` and `,`) → 400 (`InvalidSortQuery`).
- `laravel-query-wizard` 3.0 moved configuration keys (`disable_invalid_*_query_exception` → `ignore_unknown.*` and
  others); see its UPGRADE.md.
- Count and exists includes are allowed only explicitly: `allowedIncludes('comments')` does not allow `commentsCount`.
- `defaultSorts()`, `defaultIncludes()` and the other `default*()` methods replace the schema defaults; calling one
  without arguments sets no defaults.
- The `limits` config caps includes, filters, sorts, appends and, new in v3, the values one filter receives
  (`max_filter_values_count`, 1000 by default) → 400. Config values are validated when read.

### Filter Behavior

- **Value splitting.** A value is split by the filter separator once, by `laravel-query-wizard`; with
  `withoutValueSplitting()` or another separator, `Smith, John` stays one term (v2 split it on `,` a second time). The pattern and
  text filters (`prefix`, `wildcard`, `regexp`, `fuzzy`, `match`, `matchPhrase`, `matchPhrasePrefix`, `multiMatch`,
  `queryString`, `simpleQueryString`) no longer split: `red, blue` reaches Elasticsearch as sent. They take one value:
  a list is a 400 (the match family and the query string filters joined it with `,` in v2), so
  `withValueSplitting()` does not restore the v2 behavior; it turns a value containing the separator into a 400.
  `moreLikeThis` keeps a value whole too and reads a list as several texts.
- **Filter groups take no value modifiers.** `default()`, `prepareValueWith()`, `when()`, `asBoolean()`,
  `allowStructuredInput()`, `withValueSplitting()` and `withoutValueSplitting()` on a bool or nested group throw a
  `LogicException` when the group is configured, since a group has no value of its own; call them on a child filter.
- **Negated exists and null filters** stay in the filter's clause: in `inShould()` or a bool group a negated condition
  is one alternative (v2 added a `must_not` that excluded the documents from every alternative), and in `inMustNot()`
  the double negation requires the field.
- **Random sort** wraps the whole query, filters included, in a `function_score` whose `random_score` replaces the
  relevance score, and sorts by `_score`. Scoring filters such as `match` no longer affect the shuffled order.
- **Bool groups** leave out `minimum_should_match` when the request fills none of their should children, so the other
  children still match. Group configuration errors no longer wait for a request that uses the group: `children()`
  throws `UnsupportedFilterInGroupException` for a passthrough, callback or trashed child, and the build throws
  `FilterNameConflictException` for a group named like another filter or a filter in two groups.
- **Unreadable values are 400s.** `InvalidRangeValue`, `InvalidGeoBoundingBoxValue`, `InvalidGeoDistanceValue` and
  `InvalidGeoShapeValue` extend `laravel-query-wizard`'s `InvalidFilterValue`: the status is 400 (was 422), the error
  code `invalid_filter_value`, and the message names the filter by its public name. Their factories take the value and
  the filter (`InvalidRangeValue::invalidBounds($value, $filter)`, `InvalidGeoShapeValue::invalidPoint($value, $filter)`,
  …) instead of a property name; `InvalidGeoBoundingBoxValue::make()` and `InvalidGeoDistanceValue::make()` became
  `invalidBox()` and `invalidDistance()`. Values that reached Elasticsearch and failed there with a 500 are now rejected
  first: a range bound that is not a decimal number or an ISO 8601 date (`abc`, `1e3`, date math such as `now-1d`), a
  geo distance that is not a positive number with a unit, and coordinates out of range. Exists and null filters reject
  a value that is not a boolean, and the trashed filter one that is not `with`, `only`, `without`, `true` or `false`
  (`1` and `0` included); v2 ignored them.
- **Query string filters** search their property: `queryString('body', 'q')` and `simpleQueryString('body', 'q')`
  send `fields: ["body"]` (v2 left `fields` out, so Elasticsearch searched every field, and the property served only as
  the parameter name). Set `fields` (or `default_field` for `queryString`) with `withParameters()` to search others.
  `queryString` also sends `allow_leading_wildcard: false` and answers a term starting with `*` or `?` with 400;
  `withParameters(['allow_leading_wildcard' => true])` restores the v2 behavior.
- **Other indices are out of the client's reach.** A more-like-this document reference takes only an `_id` and is
  read from the searched index (v2 let the client set `_index` and any other key). A geo shape `indexed_shape` needs
  `->indexedShapes($index, $path)` on the filter and takes only an `id` from the client (v2 let the client set `index`
  and `path`, so it could read any index, and a `path` naming a plain field echoed its value in the error).
- **More like this** keeps a text whole instead of splitting it on the separator; send several texts as a list.
- **Date ranges** read dates like `laravel-query-wizard`: each bound is a date or an ISO 8601 date-time (other values,
  including epoch numbers and date math, → 400), read in the application timezone or the filter's `timezone()` (v2
  passed `timezone()` to Elasticsearch as `time_zone`, and without it Elasticsearch read dates in UTC). A date `to` covers
  the whole day. The bounds are sent as ISO 8601 date-times with an offset and `format: strict_date_optional_time`.
  `dateFormat()` is deprecated in favor of `esFormat()`. Neither changes the request format any more:
  `dateFormat('dd/MM/yyyy')` with `from=01/01/2024` is now a 400. The format given is sent followed by
  `||strict_date_optional_time`, so Elasticsearch still reads the ISO bounds.
- **Regexp patterns** longer than 1000 characters (Elasticsearch's default `index.max_regex_length`) return 400
  instead of a 500 from Elasticsearch; `maxLength()` sets another limit. The text and pattern filters take
  `maxLength()` as an opt-in limit.
- **Range filters** go to the `filter` clause (v2: `must`), so they no longer add to the relevance score; `inMust()`
  restores that.
- **Geo bounding box longitudes keep their order.** v2 swapped `left` and `right` when `left > right`; v3 reads such a
  box as crossing the antimeridian, so the same request matches the other side of the globe. Latitudes are still
  swapped when `bottom > top`.
- **Values.** A range bound left empty is no bound. A geo bounding box also takes named edges (`left`, `bottom`,
  `right`, `top`). A geo shape polygon keeps its holes and is closed when its last point differs from its first.
  Coordinates that overflow to infinity (`1e999`) are rejected instead of failing the JSON encoding with a 500.

### Filter Parameters

`withParameters()` now checks each name when the filter is configured and throws `InvalidArgumentException` for a
name the filter's query has no setter for; v2 threw `BadMethodCallException` on the first request that used the filter.
A term filter no longer accepts `case_insensitive`: its multi-value `terms` query does not support it, and
Elasticsearch rejected such a request.

### New Methods in v3

```php
// Create wizard from ResourceSchema
ElasticQueryWizard::forSchema(ProductSchema::class);

// Access bool query
$wizard->boolQuery();

// Tap SearchBuilder for custom mutations
$wizard->tapSearchBuilder(fn($sb) => $sb->highlight('title'));

// Apply post-processing to externally-loaded results
$wizard->applyPostProcessingTo($results);
```

---

## Replacing Custom QueryWizard Classes with Schemas

In v2.x, a common pattern was to create dedicated QueryWizard subclasses for each resource. Typically you needed multiple classes per resource: one for Elasticsearch collections (ElasticQueryWizard), one for Eloquent collections (EloquentQueryWizard), and one for single models (ModelQueryWizard).

### v2 (multiple classes with duplicated config)
```php
// app/QueryWizards/Products/ProductsElasticQueryWizard.php
class ProductsElasticQueryWizard extends ElasticQueryWizard
{
    protected function allowedFilters(): array
    {
        return [
            new TermFilter('status'),
            new MatchFilter('title'),
            new RangeFilter('price'),
        ];
    }

    protected function allowedSorts(): array
    {
        return [new FieldSort('created_at')];
    }

    protected function allowedIncludes(): array
    {
        return [
            new RelationshipInclude('category'),
            new CountInclude('reviews'),
        ];
    }
}

// app/QueryWizards/Products/ProductQueryWizard.php (for single models)
class ProductQueryWizard extends ModelQueryWizard
{
    protected function allowedIncludes(): array
    {
        return [
            new RelationshipInclude('category'),  // Duplicated!
            new RelationshipInclude('reviews'),
        ];
    }

    protected function allowedFields(): array
    {
        return ['id', 'title', 'price', 'status'];
    }
}

// Usage
$products = ProductsElasticQueryWizard::for(Product::class)->build()->get();
$product = ProductQueryWizard::for(Product::find(1))->build();
```

### v3 (single ResourceSchema, reusable across all wizards)
```php
// app/Schemas/ProductSchema.php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

class ProductSchema extends ResourceSchema
{
    public function model(): string
    {
        return Product::class;
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        // Return Elastic filters for ElasticQueryWizard, Eloquent filters for others
        if ($wizard instanceof ElasticQueryWizard) {
            return [
                ElasticFilter::term('status'),
                ElasticFilter::match('title'),
                ElasticFilter::range('price'),
            ];
        }

        // Eloquent filters for EloquentQueryWizard
        return [
            EloquentFilter::exact('status'),
            EloquentFilter::partial('title'),
            EloquentFilter::range('price'),
        ];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        if ($wizard instanceof ElasticQueryWizard) {
            return [ElasticSort::field('created_at')];
        }

        return ['created_at'];  // String shorthand for EloquentQueryWizard
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        // Base includes shared by all wizards
        $includes = ['category'];

        if ($wizard instanceof ElasticQueryWizard) {
            // Elastic-specific includes
            $includes[] = ElasticInclude::relationship('reviews');
            $includes[] = ElasticInclude::count('reviews');
        } elseif ($wizard instanceof EloquentQueryWizard) {
            // Eloquent-specific includes
            $includes[] = EloquentInclude::relationship('reviews');
            $includes[] = EloquentInclude::count('reviews');
        } else {
            // ModelQueryWizard - just load relations
            $includes[] = 'reviews';
        }

        return $includes;
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        $fields = ['id', 'title', 'price', 'status'];

        // Include more fields for single model requests
        if ($wizard instanceof ModelQueryWizard) {
            $fields[] = 'description';
            $fields[] = 'specifications';
        }

        return $fields;
    }

    public function appends(QueryWizardInterface $wizard): array
    {
        $appends = ['formatted_price'];

        // Heavy computed appends only for single models
        if ($wizard instanceof ModelQueryWizard) {
            $appends[] = 'related_products';
            $appends[] = 'price_history';
        }

        return $appends;
    }

    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        // Use '-' prefix for descending order (works for all wizard types)
        return ['-created_at'];
    }
}

// Usage - one schema works with all wizard types
$products = ElasticQueryWizard::forSchema(ProductSchema::class)->build()->execute();
$products = EloquentQueryWizard::forSchema(ProductSchema::class)->get();
$product = ModelQueryWizard::for(Product::find(1))->schema(ProductSchema::class)->process();
```

**Benefits:**
- **No duplication**: One schema replaces multiple classes, shared configuration stays in one place
- **Reusability**: Same schema works with `ElasticQueryWizard`, `EloquentQueryWizard`, and `ModelQueryWizard`
- **Conditional logic**: Use `instanceof` checks to return wizard-specific filters/sorts/includes
- **Flexibility**: Override schema settings per-request using `disallowed*()` methods
- **Separation of concerns**: Query configuration is separate from query execution

---

## Callback Signatures

### v2
```php
// Filter
new CallbackFilter('name', function($queryWizard, SearchParametersBuilder $builder, $value) {
    $queryWizard->getRootBoolQuery()->must(/* ... */);
});

// Sort
new CallbackSort('name', function($queryWizard, SearchParametersBuilder $builder, string $direction, string $property) {
    $builder->sort(/* ... */);
});

// Include
new CallbackInclude('name', function($queryWizard, Builder $builder, string $include) {
    $builder->with(/* ... */);
});
```

### v3
```php
// Filter
ElasticFilter::callback('name', function(SearchBuilder $builder, mixed $value, string $property) {
    $builder->must(/* ... */);
});

// Sort
ElasticSort::callback('name', function(SearchBuilder $builder, string $direction, string $property) {
    $builder->sort(/* ... */);
});

// Include
ElasticInclude::callback('name', function(Builder $builder, string $relation) {
    $builder->with(/* ... */);
});
```

---

## New Features in v3

### New Filter Types
- `ElasticFilter::exists()` — field existence
- `ElasticFilter::multiMatch()` — search across multiple fields
- `ElasticFilter::geoShape()` — geo shape queries
- `ElasticFilter::fuzzy()` — fuzzy matching
- `ElasticFilter::prefix()` — prefix matching
- `ElasticFilter::wildcard()` — wildcard patterns
- `ElasticFilter::regexp()` — regex matching
- `ElasticFilter::ids()` — document ID matching
- `ElasticFilter::matchPhrase()` — phrase matching
- `ElasticFilter::matchPhrasePrefix()` — phrase prefix
- `ElasticFilter::queryString()` — Lucene query syntax
- `ElasticFilter::simpleQueryString()` — simple query syntax
- `ElasticFilter::dateRange()` — date range with format
- `ElasticFilter::null()` — null/missing field queries
- `ElasticFilter::nested()` — nested document filtering
- `ElasticFilter::moreLikeThis()` — similar documents
- `ElasticFilter::passthrough()` — raw value passthrough

### New Sort Types
- `ElasticSort::geoDistance()` — sort by distance
- `ElasticSort::script()` — Painless script sorting
- `ElasticSort::score()` — relevance score sorting
- `ElasticSort::nested()` — nested field sorting
- `ElasticSort::random()` — random ordering with seed

### New Include Types
- `ElasticInclude::exists()` — add a `{relation}_exists` boolean attribute (e.g. `comments_exists`)

### DSL Proxy Classes
```php
// Build ES queries fluently
ElasticQuery::term('field', 'value');
ElasticQuery::bool()->must(...)->filter(...);

// Build aggregations
ElasticAggregation::terms('field');
ElasticAggregation::dateHistogram('field', '1d');
```

---

## Breaking Changes in FilterValueSanitizer

`FilterValueSanitizer` is `@internal` in v3: it reads the values of the built-in filters and may change in a minor
release. Read values in custom filters with `laravel-query-wizard`'s `Support\FilterValueParser` (`number()`,
`boolean()`, `isoDate()`, `isBlank()`, …), which throws the 400 `InvalidFilterValue` for a value it cannot read.

### Range Filter Operators

A range filter takes `gt`, `gte`, `lt` and `lte`, as in v2. The legacy `from`, `to`, `include_lower` and
`include_upper`, which Elasticsearch 9 removed, are refused with an `InvalidRangeValue` that names them; v2 refused
them as any other unknown key.

```
?filter[price][gte]=100&filter[price][lte]=500
```

---

## Elasticsearch 9.x Compatibility

v3 is compatible with ES 8.x and 9.x. Key notes:

1. **Range queries:** Use `gt/gte/lt/lte` (not `from/to`) — legacy operators throw exception
2. **Random sorting:** A seeded `random_score` needs a `field`; `ElasticSort::random()->seed()` sets `_seq_no` for you
3. **Highlighting:** `force_source` parameter removed
4. **Histogram aggregation:** Cannot use on boolean fields (use `terms` instead)
5. **Circle geo shape:** Not supported (use `GeoDistanceFilter` instead)

---

## Migration Checklist

### Dependencies
- [ ] Update `composer.json`: replace `elastic-scout-driver-plus` with `es-scout-driver`
- [ ] Update `composer.json`: require `laravel-query-wizard` `^3.0.0-rc.3` and `es-scout-driver` `^1.0.0-rc.1`

### Method Renames
- [ ] Replace `setAllowedFilters()` → `allowedFilters()`
- [ ] Replace `setAllowedSorts()` → `allowedSorts()`
- [ ] Replace `setAllowedIncludes()` → `allowedIncludes()`
- [ ] Replace `setAllowedFields()` → `allowedFields()`
- [ ] Replace `setAllowedAppends()` → `allowedAppends()`
- [ ] Replace `setDefaultSorts()` → `defaultSorts()`
- [ ] Replace `->build()->get()` → `->build()->execute()`

### Instantiation
- [ ] Replace `new TermFilter()` → `ElasticFilter::term()` or `TermFilter::make()`
- [ ] Replace `new MatchFilter()` → `ElasticFilter::match()` or `MatchFilter::make()`
- [ ] Replace `new RangeFilter()` → `ElasticFilter::range()` or `RangeFilter::make()`
- [ ] Replace `new FieldSort()` → `ElasticSort::field()` or `FieldSort::make()`
- [ ] Replace `new RelationshipInclude()` → `ElasticInclude::relationship()`
- [ ] Replace `new CountInclude()` → `ElasticInclude::count()`
- [ ] Replace `new CallbackFilter/Sort/Include()` → `ElasticFilter/Sort/Include::callback()`

### Custom Classes
- [ ] Change base class: `ElasticFilter` → `AbstractElasticFilter`
- [ ] Change base class: `ElasticSort` → `AbstractElasticSort`
- [ ] Change base class: `ElasticInclude` → `AbstractElasticInclude`
- [ ] Update `handle()` signature: remove `$queryWizard` parameter
- [ ] For includes: rename `handle()` → `handleEloquent()`
- [ ] Replace `$this->getPropertyName()` → `$this->property`
- [ ] Replace `$this->getInclude()` → `$this->relation`
- [ ] Replace custom `*QueryWizard` subclasses with `ResourceSchema` (see "Replacing Custom QueryWizard Classes with Schemas")

### API Changes
- [ ] Replace `getRootBoolQuery()->must()` → `$builder->must()`
- [ ] Replace `getRootBoolQuery()->filter()` → `$builder->filter()`
- [ ] Replace `addEloquentQueryCallback()` → `modifyQuery()`
- [ ] Replace `addEloquentCollectionCallback()` → `modifyModels()`
- [ ] Change the second `modifyQuery()` callback argument from `SearchResult` to `array $rawResult`
- [ ] Code that used the wizard returned by `build()`: `build()` now returns the `SearchBuilder`
- [ ] Replace `EloquentFilter` instances in `allowedFilters()` with Elasticsearch filters

### Namespace Updates
- [ ] Replace `Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder` → `Jackardios\EsScoutDriver\Search\SearchBuilder`
- [ ] Replace `Elastic\Adapter\Search\SearchResult` → `Jackardios\EsScoutDriver\Search\SearchResult`
- [ ] Replace `Elastic\ScoutDriverPlus\Support\Query` → `Jackardios\EsScoutDriver\Support\Query`

### Query DSL (if using Query class directly)
- [ ] Replace `Query::term()->field($f)->value($v)` → `Query::term($f, $v)`
- [ ] Replace `Query::match()->field($f)->query($v)` → `Query::match($f, $v)`
- [ ] Replace `Query::terms()->field($f)->values($v)` → `Query::terms($f, $v)`

### Testing
- [ ] Test with Elasticsearch 8.x or 9.x
- [ ] Verify all filters work correctly
- [ ] Verify all sorts work correctly
- [ ] Verify all includes load relations properly
