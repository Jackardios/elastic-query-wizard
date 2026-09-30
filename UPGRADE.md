# Upgrade Guide

This document describes how to upgrade Elastic Query Wizard between versions.

- Coming from v2.x, read [Upgrading from v2.x to 3.0](#upgrading-from-v2x-to-30).
- Coming from a `dev-master` snapshot of v3 (the `master` branch, which required `es-scout-driver` `dev-main`, or the
  `v3-rc1` branch), read [Upgrading from dev-master snapshots to 3.0](#upgrading-from-dev-master-snapshots-to-30).

Both follow `laravel-query-wizard` 3.0, whose rules apply to this package too; its
[upgrade guide](https://github.com/Jackardios/laravel-query-wizard/blob/v3.0.0-rc.4/UPGRADE.md) has the full list.

---

## Upgrading from v2.x to 3.0

Version 3 is a rewrite on top of `laravel-query-wizard` v3 and a new Elasticsearch driver, `es-scout-driver`. Most
configuration moves from subclasses and `new` to fluent calls and static factories, and most values the package used to
pass to Elasticsearch unchecked are now read first: a value it cannot read is a 400 instead of a 500 from Elasticsearch.

### Requirements

- PHP 8.2+ (tested on 8.2–8.5) and Laravel 12.69.0+ or 13.30.0+ (v2: PHP 8.1+, Laravel 10). Laravel 10 and 11 stay on
  the `legacy-l10` branch (`dev-legacy-l10`).
- Elasticsearch 8.x or 9.x, with the `elasticsearch/elasticsearch` client of the same major version.

**v2.2.0:**
```json
"php": "^8.1",
"jackardios/elastic-scout-driver-plus": "^v4.0.0",
"jackardios/laravel-query-wizard": "^v2.0.2",
"laravel/framework": "^v10.0"
```

**3.0:**
```json
"php": "^8.2",
"jackardios/es-scout-driver": "^1.0.0-rc.1",
"jackardios/laravel-query-wizard": "^3.0.0-rc.5",
"laravel/framework": "^12.69.0 || ^13.30.0"
```

Require the package with `composer require jackardios/elastic-query-wizard:^3.0@rc` while 3.0 is a release candidate.

### The Elasticsearch Driver

`elastic-scout-driver-plus` is replaced by `es-scout-driver`, a different package with its own configuration, query
API and result classes. Its
[migration guide](https://github.com/Jackardios/es-scout-driver/blob/v1.0.0-rc.1/MIGRATION_GUIDE.md) covers the
driver; the classes the wizard deals with are:

| v2 | 3.0 |
|----|-----|
| `Elastic\ScoutDriverPlus\Searchable` (model trait) | `Jackardios\EsScoutDriver\Searchable` |
| `Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder` | `Jackardios\EsScoutDriver\Search\SearchBuilder` |
| `Elastic\ScoutDriverPlus\Decorators\SearchResult` (from `execute()`) | `Jackardios\EsScoutDriver\Search\SearchResult` |
| `Elastic\Adapter\Search\SearchResult` (in include and query callbacks) | `Jackardios\EsScoutDriver\Search\SearchResult` |
| `Elastic\ScoutDriverPlus\Paginator` | `Jackardios\EsScoutDriver\Search\Paginator` |
| `$paginator->onlyModels()`, `onlyDocuments()` (change the paginator) | `withModels()`, `withDocuments()` (return a copy) |
| `Elastic\ScoutDriverPlus\Support\Query` | `Jackardios\EsScoutDriver\Support\Query` |

Searched models must use the new `Searchable` trait; for another class `ElasticQueryWizard::for()` throws
`InvalidArgumentException`
(`` `App\Models\Post` is not a model using the `Jackardios\EsScoutDriver\Searchable` trait. ``).

The query factories take their required values as arguments:

```php
// v2
Query::term()->field('status')->value('active');
Query::terms()->field('status')->values(['active', 'pending']);
Query::match()->field('title')->query('search text');
Query::range()->field('price')->gte(100)->lte(500);

// 3.0
Query::term('status', 'active');
Query::terms('status', ['active', 'pending']);
Query::match('title', 'search text');
Query::range('price')->gte(100)->lte(500);
```

### Creating the Wizard

`ElasticQueryWizard::for()` takes the model class (`ElasticQueryWizard::for(Post::class)`), and its second parameter is
named `$parameters` (was `$parametersManager`). A model instance is refused, since the search would cover the whole
index rather than that model: under `strict_types` it is a `TypeError`; otherwise PHP turns the model into its JSON and
the constructor throws `InvalidArgumentException` (`` `{"id":1,…}` is not a model using the
`Jackardios\EsScoutDriver\Searchable` trait. ``).

### Method Renames

| v2 | 3.0 |
|----|-----|
| `setAllowedFilters()` | `allowedFilters()` |
| `setAllowedSorts()` | `allowedSorts()` |
| `setAllowedIncludes()` | `allowedIncludes()` |
| `setAllowedFields()` | `allowedFields()` |
| `setAllowedAppends()` | `allowedAppends()` |
| `setDefaultSorts()` | `defaultSorts()` |
| `setDefaultIncludes()` | `defaultIncludes()` |
| `setDefaultAppends()` | `defaultAppends()` |
| `addEloquentQueryCallback()` | `modifyQuery()` |
| `addEloquentCollectionCallback()` | `modifyModels()` |
| `getRootBoolQuery()` | `boolQuery()`, or `tapSearchBuilder()` |
| `rootFieldsKey()` override | the schema's `type()` (the default is still the camel-cased model name, `fields[post]`) |
| `getPropertyName()` | `getProperty()`, or `$this->property` inside the filter or sort |
| `getInclude()` | `getRelation()`, or `$this->relation` inside the include |

`makeDefaultFilterHandler()`, `makeDefaultSortHandler()` and `makeDefaultIncludeHandler()` are removed. A string in
`allowedFilters()` is still a term filter, one in `allowedSorts()` a field sort, and one in `allowedIncludes()` a
relationship include, or a count or exists include when it ends with `Count` or `Exists`.

### Building and Running the Search

`build()` returns the `SearchBuilder` (v2 returned the wizard), so `->build()->execute()` still runs the search, and
`execute()` returns `Jackardios\EsScoutDriver\Search\SearchResult` (was
`Elastic\ScoutDriverPlus\Decorators\SearchResult`).
Code that kept using the result of `build()` as the wizard needs the wizard in its own variable.

`$wizard->execute()` and the other search builder methods called on the wizard now build the search first (v2 forwarded
them to the unbuilt search). `$wizard->paginate()` returns `Jackardios\EsScoutDriver\Search\Paginator` with 15 results
per page by default (v2 forwarded it to the driver, whose default is 10; the search builder's `paginate()` still
defaults to 10), and a page that ends past the result window is a 400 `MaxResultWindowExceeded` (see
[docs/advanced.md](docs/advanced.md#pagination)).

### Callbacks

```php
// v2
$wizard->addEloquentQueryCallback(function (Builder $builder, SearchResult $result) {
    /* … */
});
$wizard->addEloquentCollectionCallback(function (Collection $collection) {
    return $collection->filter(/* … */);
});

// 3.0
$wizard->modifyQuery(function (Builder $builder, array $rawResult) {
    /* … */
});
$wizard->modifyModels(function (Collection $collection) {
    return $collection->filter(/* … */);
});
$wizard->tapSearchBuilder(function (SearchBuilder $builder) {
    $builder->highlight('title');
});
```

The second argument of the `modifyQuery()` callback is the raw Elasticsearch response as an array; a callback that
type-hints `SearchResult` throws a `TypeError`. `modifyQuery()` and `modifyModels()` register callbacks for the next
build: after `build()` they throw a `LogicException`. `tapSearchBuilder()` runs on every build.

### The Bool Query

`ElasticRootBoolQuery` is removed. Add clauses through the search builder:

```php
// v2
$wizard->getRootBoolQuery()->must($query);
$wizard->getRootBoolQuery()->filter($query);

// 3.0
$wizard->tapSearchBuilder(fn (SearchBuilder $builder) => $builder->must($query));
// or, once the configuration is final:
$wizard->boolQuery()->filter($query);
```

`tapSearchBuilder()` runs again on every build. `boolQuery()` builds the wizard first and returns the bool query of the
built search, so a configuration call after it throws a `LogicException` instead of rebuilding without the change.

The soft delete mode moved to the search builder:

```php
// v2
$wizard->getRootBoolQuery()->withTrashed();

// 3.0
$wizard->withTrashed(); // also onlyTrashed() and excludeTrashed()
```

### Eloquent Filters

v2 accepted `EloquentFilter` instances in `setAllowedFilters()` and applied them to the Eloquent query that loads the
models. 3.0 takes Elasticsearch filters only: an Eloquent filter fails with a `TypeError` (a 500) on the first request
that uses it. Replace it with an `ElasticFilter` so the condition is part of the search and of its total.

### Filter, Sort and Include Classes

| v2 | 3.0 |
|----|-----|
| `ElasticFilter` (abstract base) | `Filters\AbstractElasticFilter`; `ElasticFilter` is now the final factory class |
| `ElasticSort` (abstract base) | `Sorts\AbstractElasticSort`; `ElasticSort` is now the final factory class |
| `ElasticInclude` (abstract base) | `Includes\AbstractElasticInclude`; `ElasticInclude` is now the final factory class |
| `ElasticRootBoolQuery` | Removed; see [The Bool Query](#the-bool-query) |
| `Filters\CallbackFilter` | `ElasticFilter::callback()` (a `laravel-query-wizard` `CallbackFilter`) |
| `Sorts\CallbackSort` | `ElasticSort::callback()` (a `laravel-query-wizard` `CallbackSort`) |
| `Includes\CallbackInclude` | `ElasticInclude::callback()` (a `laravel-query-wizard` `CallbackInclude`) |
| `Includes\RelationshipInclude` | `ElasticInclude::relationship()` (a `laravel-query-wizard` `RelationshipInclude`) |
| `Includes\CountInclude` | `ElasticInclude::count()` (a `laravel-query-wizard` `CountInclude`) |

Constructors are protected: create filters, sorts and includes with the factories or the classes' `make()`. The
built-in filters and sorts are `final`.

```php
// v2
new TermFilter('status');
new MatchFilter('title');
new FieldSort('created_at');
new RelationshipInclude('author');

// 3.0, factories (recommended)
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\ElasticSort;

ElasticFilter::term('status');
ElasticFilter::match('title');
ElasticSort::field('created_at');
ElasticInclude::relationship('author');

// 3.0, make()
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;

TermFilter::make('status');
MatchFilter::make('title');
FieldSort::make('created_at');
RelationshipInclude::make('author');
```

### Complete Example

```php
// v2
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
    ->execute();

// 3.0
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

### Callback Signatures

The callbacks no longer receive the wizard:

```php
// v2
new CallbackFilter('name', function ($queryWizard, SearchParametersBuilder $builder, $value) {
    $queryWizard->getRootBoolQuery()->must(/* … */);
});
new CallbackSort('name', function ($queryWizard, SearchParametersBuilder $builder, string $direction, string $property) {
    $builder->sort(/* … */);
});
new CallbackInclude('name', function ($queryWizard, Builder $builder, string $include) {
    $builder->with(/* … */);
});

// 3.0
ElasticFilter::callback('name', function (SearchBuilder $builder, mixed $value, string $property) {
    $builder->must(/* … */);
});
ElasticSort::callback('name', function (SearchBuilder $builder, string $direction, string $property) {
    $builder->sort(/* … */);
});
ElasticInclude::callback('name', function (Builder $builder, string $relation) {
    $builder->with(/* … */);
});
```

### Custom Filters

A filter returns its query from `buildQuery()` instead of adding it to the root bool query in `handle()`. The wizard
adds the query to the filter's clause, so `inMust()`, `inShould()`, `inMustNot()` and bool groups work with custom
filters.

**v2:**

```php
use Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder;
use Jackardios\ElasticQueryWizard\ElasticFilter;

class CustomFilter extends ElasticFilter
{
    public function handle($queryWizard, SearchParametersBuilder $builder, $value): void
    {
        $queryWizard->getRootBoolQuery()->must(/* … */);
    }
}

new CustomFilter('property_name');
```

**3.0:**

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

CustomFilter::make('property_name');
```

- Extend `AbstractElasticFilter` and replace `handle()` with `buildQuery(mixed $value): QueryInterface|array|null`;
  return `null` to add nothing.
- The default clause is `filter`; override `getDefaultClause()` to change it.
- Read the property from `$this->property` and add a static `make()`.
- Read values with `laravel-query-wizard`'s `Support\FilterValueParser` (`number()`, `boolean()`, `isoDate()`,
  `isBlank()`, …), which throws the 400 `InvalidFilterValue` for a value it cannot read. `FilterValueSanitizer` is
  `@internal`.

### Custom Sorts

**v2:**

```php
use Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder;
use Jackardios\ElasticQueryWizard\ElasticSort;

class CustomSort extends ElasticSort
{
    public function handle($queryWizard, SearchParametersBuilder $builder, string $direction): void
    {
        $builder->sort($this->getPropertyName(), $direction);
    }
}
```

**3.0:**

```php
use Jackardios\ElasticQueryWizard\Sorts\AbstractElasticSort;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Enums\SortDirection;

final class CustomSort extends AbstractElasticSort
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function handle(SearchBuilder $builder, SortDirection $direction): void
    {
        $builder->sort(Sort::field($this->property)->order($direction->value));
    }
}
```

`handle()` takes the `SearchBuilder` and a `SortDirection` enum instead of the wizard, the builder and an
`'asc'`/`'desc'` string; `$direction->value` is the string.

### Custom Includes

**v2:**

```php
use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\ElasticInclude;

class CustomInclude extends ElasticInclude
{
    public function handle($queryWizard, Builder $eloquentBuilder): void
    {
        $eloquentBuilder->with($this->getInclude());
    }
}
```

**3.0:**

```php
use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Jackardios\QueryWizard\Contracts\EagerLoadsRelation;

final class CustomInclude extends AbstractElasticInclude implements EagerLoadsRelation
{
    public static function make(string $relation, ?string $alias = null): static
    {
        return new static($relation, $alias);
    }

    public function handleEloquent(Builder $eloquentBuilder): void
    {
        $eloquentBuilder->with($this->relation);
    }
}
```

- Replace `handle($queryWizard, Builder $builder)` with `handleEloquent(Builder $eloquentBuilder)`, and read the
  relation from `$this->relation`.
- An include that eager loads the relation `getRelation()` names implements `laravel-query-wizard`'s
  `Contracts\EagerLoadsRelation`; relation fieldsets (`fields[relation]=…`) apply only to such includes.
- `getSearchResult()` returns `?SearchResult` (`Jackardios\EsScoutDriver\Search\SearchResult`); it is `null` before the
  search ran.

`AbstractElasticFilter`, `AbstractElasticSort` and `AbstractElasticInclude` are marked `@api`; the README's
[Backward Compatibility](README.md#backward-compatibility) section lists what 3.x keeps stable for subclasses.

### Replacing QueryWizard Subclasses with Schemas

In v2, a resource often had one wizard subclass per wizard type, with the configuration repeated in each:

```php
// v2
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

class ProductQueryWizard extends ModelQueryWizard
{
    protected function allowedIncludes(): array
    {
        return [
            new RelationshipInclude('category'),
            new RelationshipInclude('reviews'),
        ];
    }

    protected function allowedFields(): array
    {
        return ['id', 'title', 'price', 'status'];
    }
}

$products = ProductsElasticQueryWizard::for(Product::class)->build()->execute();
$product = ProductQueryWizard::for(Product::find(1))->build();
```

In 3.0 `allowedFilters()` and the other configuration methods are public and take the definitions, so these
overrides fail. One `ResourceSchema` serves every wizard:

```php
// 3.0
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;

class ProductSchema extends ResourceSchema
{
    public function model(): string
    {
        return Product::class;
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        if ($wizard instanceof ElasticQueryWizard) {
            return [
                ElasticFilter::term('status'),
                ElasticFilter::match('title'),
                ElasticFilter::range('price'),
            ];
        }

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

        return ['created_at'];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        $includes = ['category'];

        if ($wizard instanceof ElasticQueryWizard) {
            $includes[] = ElasticInclude::relationship('reviews');
            $includes[] = ElasticInclude::count('reviews');
        } elseif ($wizard instanceof EloquentQueryWizard) {
            $includes[] = EloquentInclude::relationship('reviews');
            $includes[] = EloquentInclude::count('reviews');
        } else {
            $includes[] = 'reviews';
        }

        return $includes;
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        $fields = ['id', 'title', 'price', 'status'];

        if ($wizard instanceof ModelQueryWizard) {
            $fields[] = 'description';
        }

        return $fields;
    }

    public function appends(QueryWizardInterface $wizard): array
    {
        return ['formatted_price'];
    }

    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        return ['-created_at'];
    }
}

$products = ElasticQueryWizard::forSchema(ProductSchema::class)->build()->execute();
$products = EloquentQueryWizard::forSchema(ProductSchema::class)->get();
$product = ModelQueryWizard::for(Product::find(1))->schema(ProductSchema::class)->process();
```

A controller narrows a schema with the `disallowed*()` methods and extends it with `addAllowedFilters()` and the other
`addAllowed*()` methods.

### Request Handling (laravel-query-wizard 3.0)

The request is read by `laravel-query-wizard` 3.0. Read its
[upgrade guide](https://github.com/Jackardios/laravel-query-wizard/blob/v3.0.0-rc.4/UPGRADE.md): its v2.x section, then
its `dev-master → 3.0.0` section, as it advises. The changes most visible here:

- **Configuration.** The v2 keys of `config/query-wizard.php` moved: `count_suffix` to `includes.count_suffix`,
  `array_value_separator` to `separators.default`, `disable_invalid_filter_query_exception` to `ignore_unknown.filters`.
  A published v2 config file throws `InvalidArgumentException` naming the new key
  (`` Config `query-wizard.count_suffix` has moved to `query-wizard.includes.count_suffix`. ``). Config values are
  validated when read.
- **Limits.** The `limits` config (new in v3) caps each request with a 400: `max_includes_count` (10),
  `max_include_depth` (3), `max_filters_count` (20), `max_filter_values_count` (1000 values in one filter),
  `max_fields_count` (100 across every fieldset), `max_appends_count` (20), `max_append_depth` (3) and
  `max_sorts_count` (5). `null` lifts a limit.
- **Booleans.** `?filter[x]=true` and `false` stay strings: a term filter searches for the text `"true"` and a callback
  receives the string. Call `asBoolean()` on the filter to read `true/false/1/0/yes/no/on/off` as a boolean; other
  values are 400s.
- **Includes.** Count and exists includes are allowed only explicitly: `allowedIncludes('comments')` does not allow
  `commentsCount`, and `allowedIncludes('posts.comments')` does not allow `posts` or `postsCount` (v2 allowed the
  parents and their counts).
- **Allowed lists.** `allowed*()` replaces the list (and the schema's), `addAllowed*()` adds to it, and `disallowed*()`
  calls add up. Two definitions with one public name throw `InvalidArgumentException`.
- **Defaults.** `defaultSorts()`, `defaultIncludes()` and the other `default*()` methods apply without being allowed and
  replace the schema's defaults; a default that `disallowed*()` removes throws `InvalidArgumentException`.
- **Shapes.** A filter value of the wrong shape (a list where the filter takes one value) is a 400
  (`InvalidFilterQuery`), and so is an empty `?sort=` (also `-` and `,`). Blank values are absent: `null`, a
  whitespace-only string, `,` and a list of blanks add no condition.

### Sparse Fieldsets

Fieldsets follow `laravel-query-wizard`'s `EloquentQueryWizard`. An explicit empty root fieldset (`?fields[post]=`)
hides every root attribute; v2 returned every column. Without a `fields` parameter for the resource the models keep
every attribute, as before. The primary key and the Scout key are always selected, and hidden unless requested (v2
selected only the requested columns). A root fieldset keeps the count and exists includes, the selects added in
`modifyQuery()` (hidden unless requested) and the keys of the eager loads registered there.

### Filter Behavior

The filters v2 had behave as follows in 3.0; the others are [new in v3](#new-in-v3).

- **Unreadable values are 400s.** `InvalidRangeValue`, `InvalidGeoBoundingBoxValue` and `InvalidGeoDistanceValue`
  extend `laravel-query-wizard`'s `InvalidFilterValue`: the status is 400 (was 422), the error code
  `invalid_filter_value`, and the message names the filter by its public name. The classes are `final`, and their
  factories take the value and the filter: `InvalidRangeValue::invalidBounds($value, $filter)`,
  `InvalidGeoBoundingBoxValue::invalidBox()` and `InvalidGeoDistanceValue::invalidDistance()`. `make()` still exists
  but is `InvalidFilterValue::make($value, $filter, $reason)`: `InvalidRangeValue::make('price')` compiles and reports
  `price` as the rejected value.
- **Range bounds are numbers or ISO 8601 dates.** Date math (`now-1d`) and custom formats (`01/02/2024`), which v2
  passed to Elasticsearch, are 400s now (``Expected a number or an ISO 8601 date for `gte`.``). Compute the date in the
  client, use a `dateRange()` filter, or a callback filter that builds the range query. `asNumber()` accepts decimal
  numbers only. The legacy `from`, `to`, `include_lower` and `include_upper` keys are refused with a message that
  names the replacement; v2 refused them as any other unknown key.
- **Range filters** go to the `filter` clause (v2: `must`), so they no longer add to the relevance score; `inMust()`
  restores that.
- **Term filters** no longer accept `case_insensitive`. It worked for a single value in v2, but the filter sends a
  multi-value `terms` query, which has no such option, and `withParameters(['case_insensitive' => true])` now throws
  `InvalidArgumentException`. Index the field with a lowercase normalizer and lowercase the request value:
  `->prepareValueWith(fn ($value) => mb_strtolower($value))`.
- **Match filters** keep a value whole (`red, blue` is one query text) and take one value: a list is a 400 (v2 joined
  it with `,`). `withValueSplitting()` does not restore the v2 behavior; it turns a value containing the separator into
  a 400.
- **Geo bounding box longitudes keep their order.** v2 swapped `left` and `right` when `left > right`; 3.0 reads such
  a box as crossing the antimeridian, so the same request matches the other side of the globe. Latitudes are still
  swapped when `bottom > top`. Coordinates out of range are 400s, and the box also takes named edges (`left`,
  `bottom`, `right`, `top`).
- **Geo distances** take a positive number with an optional unit (`3km`) and coordinates in range; other values and
  keys other than `lat`, `lon` and `distance` are 400s (v2 passed any distance string to Elasticsearch).
- **Trashed filters** take `with`, `only`, `without`, `true` or `false`; other values (`1` and `0` included) are 400s
  (v2 ignored them). Without `scout.soft_delete` set to true, a trashed filter throws a `LogicException` (v2 did
  nothing: Scout does not index which models are trashed).
- **Filter parameters.** `withParameters()` adds to the parameters set before (v2 replaced them), and checks each name
  when the filter is configured: a name the filter's query has no setter for throws `InvalidArgumentException` (v2
  failed with a PHP `Error` on the first request that used the filter).

### New in v3

- Filters: `exists()`, `null()` and `notNull()`, `multiMatch()`, `matchPhrase()`, `matchPhrasePrefix()`, `prefix()`,
  `wildcard()`, `regexp()`, `fuzzy()`, `ids()`, `queryString()`, `simpleQueryString()`, `dateRange()`, `geoShape()`,
  `nested()`, `moreLikeThis()` and `passthrough()`.
- Filter groups: `ElasticGroup::bool()` and `ElasticGroup::nested()`, and the clause methods `inFilter()`, `inMust()`,
  `inShould()` and `inMustNot()` on every filter.
- Filter modifiers: `asNumber()` on term, range and ids filters; `maxLength()` on the text, pattern and
  more-like-this filters.
- Sorts: `ElasticSort::geoDistance()`, `script()`, `score()`, `nested()` and `random()`.
- Includes: `ElasticInclude::exists()` adds a `{relation}_exists` attribute.
- `ElasticQueryWizard::forSchema()` and resource schemas, `tapSearchBuilder()`, `boolQuery()` and
  `applyPostProcessingTo()` for models loaded outside the wizard.
- `ElasticQuery` and `ElasticAggregation`, proxies to the `es-scout-driver` query and aggregation factories:

```php
ElasticQuery::term('field', 'value');
ElasticQuery::bool()->must(ElasticQuery::match('title', 'text'))->filter(ElasticQuery::term('status', 'active'));
ElasticAggregation::terms('field');
ElasticAggregation::dateHistogram('field', '1d');
```

### Elasticsearch 9

3.0 supports Elasticsearch 8.x and 9.x:

1. **Range queries** take `gt`, `gte`, `lt` and `lte`; Elasticsearch 9 removed `from`, `to`, `include_lower` and
   `include_upper`, and the range filter refuses them.
2. **Random sorts:** a seeded `random_score` needs a `field`; `ElasticSort::random()->seed()` sets `_seq_no`.
3. **Circle geo shapes** are not supported; use `ElasticFilter::geoDistance()`.

### Checklist

Dependencies:
- [ ] Require `jackardios/elastic-query-wizard` `^3.0@rc`, `jackardios/laravel-query-wizard` `^3.0.0-rc.5` and
  `jackardios/es-scout-driver` `^1.0.0-rc.1`; remove `jackardios/elastic-scout-driver-plus`
- [ ] Follow the `es-scout-driver` migration guide: configuration, `Searchable` trait, result and paginator classes
- [ ] Move the v2 keys of `config/query-wizard.php` to their new places

Wizard:
- [ ] Pass the model class to `ElasticQueryWizard::for()`
- [ ] Rename `setAllowed*()` → `allowed*()`, `setDefault*()` → `default*()`
- [ ] Rename `addEloquentQueryCallback()` → `modifyQuery()` and type its second argument as `array $rawResult`
- [ ] Rename `addEloquentCollectionCallback()` → `modifyModels()`
- [ ] Replace `getRootBoolQuery()` with `tapSearchBuilder()` or `boolQuery()`, and `getRootBoolQuery()->withTrashed()`
  with `$wizard->withTrashed()`
- [ ] Keep the wizard in a variable where code used the result of `build()` as the wizard
- [ ] Check `paginate()` calls without a page size (15 per page now)
- [ ] Replace wizard subclasses with a `ResourceSchema`
- [ ] Replace `EloquentFilter` instances in the filter list with Elasticsearch filters

Filters, sorts and includes:
- [ ] Replace `new TermFilter()`, `new MatchFilter()`, `new RangeFilter()`, `new GeoBoundingBoxFilter()`,
  `new GeoDistanceFilter()` and `new TrashedFilter()` with `ElasticFilter::term()`, `match()`, `range()`,
  `geoBoundingBox()`, `geoDistance()` and `trashed()`
- [ ] Replace `new FieldSort()` with `ElasticSort::field()`
- [ ] Replace `new RelationshipInclude()` and `new CountInclude()` with `ElasticInclude::relationship()` and `count()`,
  and allow the count includes and the parents of nested includes the client requests
- [ ] Replace `new CallbackFilter/CallbackSort/CallbackInclude()` with `::callback()` and drop the `$queryWizard`
  argument
- [ ] Add `asBoolean()` to filters that received `true`/`false` as booleans
- [ ] Replace date math and custom date formats in range filters; drop `case_insensitive` from term filters

Custom classes:
- [ ] Filters: extend `AbstractElasticFilter` and replace `handle()` with
  `buildQuery(mixed $value): QueryInterface|array|null`
- [ ] Sorts: extend `AbstractElasticSort` and implement `handle(SearchBuilder $builder, SortDirection $direction)`
- [ ] Includes: extend `AbstractElasticInclude`, implement `handleEloquent(Builder $eloquentBuilder)` and, for an
  eager load, `EagerLoadsRelation`
- [ ] Replace `getPropertyName()` → `$this->property`, `getInclude()` → `$this->relation`, and add a static `make()`
- [ ] Replace `FilterValueSanitizer` calls with `FilterValueParser`, and `InvalidRangeValue::make()` and the other
  `make()` calls with the new factories

Tests:
- [ ] Run the suite against Elasticsearch 8.x or 9.x and check the responses of the requests that now return 400

---

## Upgrading from dev-master snapshots to 3.0

The snapshots are the `master` branch (last commit `12c14af`, requiring `es-scout-driver` `dev-main` and
`laravel-query-wizard` `dev-master`) and the `v3-rc1` branch. [Since the v3-rc1 snapshot](#since-the-v3-rc1-snapshot)
lists the changes made after `e72a4f6`, the last pushed `v3-rc1` commit; [Since the master
snapshot](#since-the-master-snapshot) lists the earlier ones, which apply when coming from `master`.
[CHANGELOG.md](CHANGELOG.md) has every entry.

### Requirements

- PHP 8.2+ (tested on 8.2–8.5) and Laravel 12.69.0+ or 13.30.0+ (`master`: PHP 8.1+, Laravel 10–12). Laravel 10 and
  11 stay on the `legacy-l10` branch (`dev-legacy-l10`), which keeps the `master` API with `es-scout-driver` `^0.1`.
- `jackardios/laravel-query-wizard` `^3.0.0-rc.5` and `jackardios/es-scout-driver` `^1.0.0-rc.1`; require this
  package as `^3.0@rc`. Read `laravel-query-wizard`'s `dev-master → 3.0.0` section too.

### Since the v3-rc1 snapshot

#### Factory Argument Order

The factories that take more than a name take the name first, like `laravel-query-wizard`'s, and so do the `make()`
methods of their classes:

| Before | 3.0 |
|--------|-----|
| `ElasticFilter::multiMatch(['title', 'body'], 'q')` | `ElasticFilter::multiMatch('q', ['title', 'body'])` |
| `ElasticFilter::moreLikeThis(['title'], 'similar')` | `ElasticFilter::moreLikeThis('similar', ['title'])` |
| `ElasticFilter::nested('comments', 'author')` | `ElasticFilter::nested('author', 'comments')` |
| `ElasticSort::script($source, 'weighted')` | `ElasticSort::script('weighted', $source)` |
| `ElasticSort::nested('variants', 'price', 'lowest_price')` | `ElasticSort::nested('lowest_price', 'variants', 'price')` |

> **Warning:** `multiMatch()` and `moreLikeThis()` calls in the old order fail with a `TypeError`, but `nested()` and
> `script()` take strings only, so an old call still runs with the arguments swapped: the filter reads the path as its
> name and the name as its path. Search the code for these four calls. Named arguments
> (`ElasticFilter::nested(property: 'author', path: 'comments')`) work in both versions.

#### Exceptions

| Before | 3.0 |
|--------|-----|
| `Exceptions\DuplicateGroupChildFilterNameException` | `Exceptions\DuplicateGroupChildFilterName`, created with `forGroup()` |
| `Exceptions\UnsupportedFilterInGroupException` (a `RuntimeException`) | `Exceptions\UnsupportedFilterInGroup`, an `InvalidArgumentException` created with `forFilter()` |
| `Exceptions\FilterNameConflictException` | `Exceptions\FilterNameConflict` |
| `FilterNameConflictException::groupNameTaken()` | Removed: a group named like another filter throws `laravel-query-wizard`'s `InvalidArgumentException` |

The exceptions are `final`, and messages across the package follow `laravel-query-wizard`'s: names in backticks, and
`Call to undefined method …()` for a method the wizard, `ElasticQuery` or `ElasticAggregation` does not have. Update
tests that compare messages.

#### Removed and Restricted

- `DateRangeFilter::dateFormat()` is removed; call `esFormat()`, which does not change the request format:
  `esFormat('dd/MM/yyyy')` with `from=01/01/2024` is a 400. The format given is sent followed by
  `||strict_date_optional_time`, so Elasticsearch still reads the ISO bounds.
- A more-like-this filter takes document references (`filter[similar][_id]=5`) only after
  `->allowDocumentReferences()`; without it a reference is a 400 (`Expected a text or a list of texts: this filter does
  not take document references.`). Elasticsearch reads the referenced document regardless of the search's
  conditions, so a client could learn what another tenant's document contains.
- `prefix` values longer than 1000 characters and `fuzzy` values longer than 256 are 400s by default, like `regexp`
  (1000 already); `maxLength()` sets another limit and `maxLength(null)` removes it.
- A trashed filter throws a `LogicException` when `scout.soft_delete` is not true: `only` returned live models, since
  Scout had not indexed which models are trashed.
- A date range filter returns 400 for keys other than its two bounds and for a year after 9999, and `withParameters()`
  refuses the parameters it sets itself (`format`, `time_zone`, `gt`, `gte`, `lt`, `lte`).
- Geo distance and geo shape filters return 400 for a key they do not read, and a distance too long to be a finite
  number is a 400.
- Text and pattern filters and `ids` return 400 for a JSON boolean from a request body.
- `ElasticSort::callback()` throws a `LogicException` when its subject is not a `SearchBuilder`, like
  `ElasticFilter::callback()`.
- `modifyQuery()`, `modifyModels()`, `tapSearchBuilder()` and search builder methods called while the wizard builds
  (from a callback) throw a `LogicException`.
- `boolQuery()` builds the wizard first. A configuration call after a change to the built search through the wizard
  (`boolQuery()`, `getBoolQuery()` or a search builder method called after the build) throws a `LogicException`, since
  the rebuild would drop the change; configure the wizard before, or use `tapSearchBuilder()`.
- `when()` and `unless()` are applied to the search builder with the other fluent calls when the wizard builds, and
  throw `BadMethodCallException` without a callback.
- `withParameters()` refuses a value of a type the query's setter does not take (`'boost' => '2'`) when the filter is
  configured, and `range()->withParameters()` refuses `gt`, `gte`, `lt` and `lte`.
- Geo coordinates in exponent notation (`1e1`) or with a trailing dot (`5.`) are 400s.
- `multiMatch()` with an empty field list throws `InvalidArgumentException`.
- `AbstractElasticGroup::addQueryToBoolQuery()` and `AbstractElasticFilter::isBlankValueShape()` are removed: add a
  query to the clause the filter's `getEffectiveClause()` names, and check values with `laravel-query-wizard`'s
  `FilterValueParser::isBlank()`.

#### Filter Groups

- `disallowedFilters()` removes a filter inside a bool or nested group, as it removes one at the root: its request key
  is refused and its default is not applied (it used to reach Elasticsearch). This needs `laravel-query-wizard`
  `^3.0.0-rc.5`.
- A schema `defaultFilters()` key names a group's leaf, as request keys do; a key naming a group throws.
- A root filter with a `default()` that a group leaf of the same name shadows throws `FilterNameConflict`; its default
  was dropped silently.
- `GroupInterface` requires `getEffectiveClause()`. Groups extending `AbstractElasticGroup` have it; a class that
  implements the interface directly adds it.

#### API Marking

- `HasParameters::applyParametersOnQuery()` is protected.
- The `Concerns` traits, `AbstractElasticInclude::setSearchResult()`, `FilterValueSanitizer` and the protected internals
  of `ElasticQueryWizard` are `@internal`.
- `AbstractElasticFilter`, `AbstractElasticSort`, `AbstractElasticInclude`, `AbstractElasticGroup` and `GroupInterface`
  are `@api`. The README's [Backward Compatibility](README.md#backward-compatibility) section lists what 3.x keeps
  stable.

#### Added

- `asNumber()` on term, range and ids filters reads values as decimal numbers and returns 400 for anything else, such
  as text or a date for a numeric field, where Elasticsearch fails the search.
- `tapSearchBuilder()`, `modifyQuery()` and `modifyModels()` take any callable (were `Closure` only).
- `ElasticQuery` and `ElasticAggregation` forward the macros of `Query` and `Agg`, and the wizard those of
  `SearchBuilder`. The wizard lists the search builder methods it forwards in `@method` tags instead of
  `@mixin SearchBuilder`, so static analysis reads a forwarded fluent call as returning the wizard.

### Since the master snapshot

These changes were made between `master` (`12c14af`) and `e72a4f6`; coming from `master`, apply
[Since the v3-rc1 snapshot](#since-the-v3-rc1-snapshot) as well.

#### Wizard

- `ElasticQueryWizard::for()` and the constructor take the model class only. A model instance, which searched the
  whole index rather than that model, is a `TypeError` under `strict_types`; otherwise PHP turns the model into its
  JSON and the constructor throws `InvalidArgumentException`
  (`` `{"id":1,…}` is not a model using the `Jackardios\EsScoutDriver\Searchable` trait. ``).
- A schema whose `model()` is not the searched model throws `InvalidArgumentException`, and filters added with
  `addAllowedFilters()` are checked for name conflicts with the others.
- `$wizard->paginate()` returns 15 results per page by default; `master` forwarded it to the search builder, whose
  default is 10. A page that ends past `elastic-query-wizard.max_result_window` (10000) is a 400
  `MaxResultWindowExceeded`.
- The soft delete mode lives on the search builder, as in `es-scout-driver` 1.0: `$wizard->boolQuery()->withTrashed()`
  becomes `$wizard->withTrashed()` (also `onlyTrashed()` and `excludeTrashed()`).
- The searched models are loaded through `laravel-query-wizard`'s `EloquentShape`, so includes, sparse fieldsets and
  appends behave as in `EloquentQueryWizard`. An explicit empty root fieldset (`?fields[post]=`) and a root fieldset
  whose fields were all dropped as invalid (with `ignore_unknown.fields` on) hide every root attribute; both returned
  every column. The primary and Scout keys are selected and hidden unless requested, and a relation fieldset runs
  after the eager-load constraint registered in `modifyQuery()` and keeps the related model's `$with` and
  `$withCount`.
- The protected internals `applyPostProcessingToResults()`, `finalizeSubject()`, `addBuildQueryModifier()`,
  `prepareAppendTreeData()`, `prepareRelationFieldData()`, the `$appendTree`, `$relationFieldTree`,
  `$buildQueryModifiers`, `$safeRootHiddenFields` and `$validatedRequestedRootFields` properties and the
  `HandlesSafeRelationSelect` and `HandlesRelationPostProcessing` traits are removed; use `modifyQuery()`,
  `modifyModels()` and `tapSearchBuilder()`.
- `applyPostProcessingTo()` returns a new lazy collection for a lazy collection and throws `InvalidArgumentException`
  for a generator, which post-processing would use up.

#### Renames

- `BoolClause` cases are PascalCase: `Filter`, `Must`, `Should`, `MustNot` (were `FILTER`, … `MUST_NOT`).
- `AbstractElasticSort::handle()` and `apply()` take a `SortDirection` (was `'asc'`/`'desc'`); pass
  `$direction->value` to `order()`. Callback sorts still receive the string.
- `ElasticFilter::notNull()` (`NullFilter::notNull()`) replaces `ElasticFilter::null()->withInvertedLogic()`
  (`withInvertedLogic()` and `withoutInvertedLogic()` are removed), and `withStructuredInput()` replaces
  `allowStructuredInput()`.
- `getType()` is removed from filters, sorts, includes and groups. A custom include that eager loads its relation
  implements `laravel-query-wizard`'s `Contracts\EagerLoadsRelation` to get relation fieldsets.

#### Filter Values

- **Value splitting.** A value is split by the filter separator once, by `laravel-query-wizard`; with
  `withoutValueSplitting()` or another separator, `Smith, John` stays one term (`master` split it on `,` a second
  time). The pattern and text filters (`prefix`, `wildcard`, `regexp`, `fuzzy`, `match`, `matchPhrase`,
  `matchPhrasePrefix`, `multiMatch`, `queryString`, `simpleQueryString`) keep the value whole and take one value: a list
  is a 400 (the match family and the query string filters joined it with `,`). `moreLikeThis` keeps a text whole too
  and reads a list as several texts.
- **Unreadable values are 400s.** `InvalidRangeValue`, `InvalidGeoBoundingBoxValue`, `InvalidGeoDistanceValue` and
  `InvalidGeoShapeValue` extend `laravel-query-wizard`'s `InvalidFilterValue` (was 422) and name the filter by its
  public name; their factories take the value and the filter (`InvalidRangeValue::invalidBounds($value, $filter)`,
  `InvalidGeoShapeValue::invalidPoint($value, $filter)`, …), and `InvalidGeoBoundingBoxValue::make()` and
  `InvalidGeoDistanceValue::make()` became `invalidBox()` and `invalidDistance()`. Range bounds must be decimal numbers
  or ISO 8601 dates (date math such as `now-1d` is a 400), geo distances positive numbers with a unit, and coordinates
  in range. Exists and null filters take booleans, and the trashed filter `with`, `only`, `without`, `true` or `false`
  (`1` and `0` are 400s).
- **`asBoolean()`** throws `LogicException` on the text, pattern, more-like-this, range, date range, geo, ids and
  trashed filters and on groups, which can't take booleans; term, exists, null and nested filters accept it.
- **Filter groups take no value modifiers.** `default()`, `prepareValueWith()`, `when()`, `asBoolean()`,
  `withStructuredInput()`, `withoutStructuredInput()`, `withValueSplitting()` and `withoutValueSplitting()` on a bool or
  nested group throw a `LogicException`; call them on a child filter.
- **`withParameters()`** checks each name when the filter is configured and throws `InvalidArgumentException` for a
  name the filter's query has no setter for; `master` threw `BadMethodCallException` on the first request that used the
  filter. The term filter refuses `case_insensitive`, which its multi-value `terms` query does not support; use a
  lowercase normalizer and `prepareValueWith(fn ($value) => mb_strtolower($value))`.

#### Filters

- **Negated exists and null filters** stay in the filter's clause: in `inShould()` or a bool group a negated condition
  is one alternative (`master` added a `must_not` that excluded the documents from every alternative), and in
  `inMustNot()` the double negation requires the field.
- **Random sorts** wrap the whole query, filters included, in a `function_score` whose `random_score` replaces the
  relevance score, and sort by `_score`. Scoring filters such as `match` no longer affect the shuffled order.
- **Bool groups** leave out `minimum_should_match` when the request fills none of their should children, so the other
  children still match. `children()` throws `UnsupportedFilterInGroup` for a passthrough, callback or trashed child,
  and the build throws `FilterNameConflict` for a filter in two groups, instead of misbehaving on a request.
- **Query string filters** search their property: `queryString('body', 'q')` and `simpleQueryString('body', 'q')`
  send `fields: ["body"]` (`master` left `fields` out, so Elasticsearch searched every field). Set `fields` (or
  `default_field` for `queryString`) with `withParameters()` to search others. `queryString` also sends
  `allow_leading_wildcard: false` and answers a term starting with `*` or `?` with 400;
  `withParameters(['allow_leading_wildcard' => true])` restores the old behavior.
- **Other indices are out of the client's reach.** A more-like-this reference takes only an `_id`, read from the
  searched index (`master` let the client set `_index`). A geo shape `indexed_shape` needs `->indexedShapes($index,
  $path)` on the filter and takes only an `id` from the client (`master` let the client set `index` and `path`).
- **Date ranges** read dates like `laravel-query-wizard`: each bound is a date or an ISO 8601 date-time (other values,
  including epoch numbers and date math, are 400s), read in the application timezone or the filter's `timezone()`
  (`master` passed `timezone()` to Elasticsearch as `time_zone`, and without it Elasticsearch read dates in UTC). A date
  `to` covers the whole day. The bounds are sent as ISO 8601 date-times with an offset and
  `format: strict_date_optional_time`.
- **Regexp patterns** longer than 1000 characters (Elasticsearch's default `index.max_regex_length`) are 400s.
- **Geo shapes:** a polygon keeps its holes and is closed when its last point differs from its first; points are
  checked like bounding box and distance coordinates.

### Checklist

- [ ] Require `jackardios/elastic-query-wizard` `^3.0@rc`, `jackardios/laravel-query-wizard` `^3.0.0-rc.5` and
  `jackardios/es-scout-driver` `^1.0.0-rc.1`
- [ ] Reorder the arguments of `multiMatch()`, `moreLikeThis()`, `ElasticFilter::nested()`, `ElasticSort::script()`
  and `ElasticSort::nested()`
- [ ] Rename the `*Exception` classes and update tests that compare messages
- [ ] Replace `dateFormat()` with `esFormat()`
- [ ] Add `allowDocumentReferences()` to more-like-this filters that take `_id` references
- [ ] Set `maxLength()` on `prefix` and `fuzzy` filters that need longer values
- [ ] Set `scout.soft_delete` to true where a trashed filter is used
- [ ] Stop calling `applyParametersOnQuery()` from outside the filter
- [ ] Add `getEffectiveClause()` to classes that implement `GroupInterface` directly
- [ ] From `master`: pass the model class to `for()`, rename the `BoolClause` cases, take `SortDirection` in custom
  sorts, replace `withInvertedLogic()` and `allowStructuredInput()`, and move `boolQuery()->withTrashed()` to the wizard
