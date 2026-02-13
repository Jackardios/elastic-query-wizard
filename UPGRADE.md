# Migration Guide: v2 → v3 (dev-master)

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
"jackardios/es-scout-driver": "dev-main",
"jackardios/laravel-query-wizard": "dev-master",
"laravel/framework": "^v10.0 || ^v11.0 || ^v12.0"
```

> **Important:** The underlying ES driver changed from `elastic-scout-driver-plus` to `es-scout-driver`. This is a completely different package with different APIs.

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
| `getPropertyName()` | `$this->property` (public property) |
| `getInclude()` | `$this->relation` (public property) |
| `addEloquentQueryCallback()` | `modifyQuery()` |
| `addEloquentCollectionCallback()` | `modifyModels()` |
| `getRootBoolQuery()` | `boolQuery()` or use `$builder` directly |
| `->build()->get()` | `->build()->execute()` |

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
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\EsScoutDriver\Search\SearchBuilder;

final class CustomFilter extends AbstractElasticFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'custom';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $propertyName = $this->property;  // Not getPropertyName()!
        $builder->must(/* ... */);
    }
}

// Usage
CustomFilter::make('property_name');
```

**Key changes:**
- Base class: `ElasticFilter` → `AbstractElasticFilter`
- Class should be `final`
- `$queryWizard` parameter removed from `handle()`
- `SearchParametersBuilder` → `SearchBuilder`
- No more `getRootBoolQuery()` — call methods directly on `$builder`
- Must implement `getType(): string` method
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

    public function getType(): string
    {
        return 'custom';
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

    public function getType(): string
    {
        return 'custom';
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
- Must implement `getType(): string` method
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
$wizard->boolQuery()->must($query);
// Or in filters, use $builder directly:
$builder->must($query);
$builder->filter($query);
$builder->should($query);
$builder->mustNot($query);
```

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
$product = ModelQueryWizard::forSchema(ProductSchema::class, Product::find(1))->process();
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
- `ElasticFilter::geoBoundingBox()` — geo bounding box queries
- `ElasticFilter::geoDistance()` — geo distance queries
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
- `ElasticFilter::trashed()` — soft delete filtering
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
- `ElasticInclude::exists()` — add `has_*` boolean attribute

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

### Range Filter Operators

Legacy range operators are no longer supported and will throw `InvalidRangeValue`:

**v2 (accepted):**
```
?filter[price][from]=100&filter[price][to]=500
?filter[price][include_lower]=true
```

**v3 (required):**
```
?filter[price][gte]=100&filter[price][lte]=500
```

Only ES 9.x compatible operators are allowed: `gt`, `gte`, `lt`, `lte`.

---

## Elasticsearch 9.x Compatibility

v3 is compatible with ES 8.x and 9.x. Key notes:

1. **Range queries:** Use `gt/gte/lt/lte` (not `from/to`) — legacy operators throw exception
2. **Random sorting:** Requires explicit `field` parameter when using `seed`
3. **Highlighting:** `force_source` parameter removed
4. **Histogram aggregation:** Cannot use on boolean fields (use `terms` instead)
5. **Circle geo shape:** Not supported (use `GeoDistanceFilter` instead)

---

## Migration Checklist

### Dependencies
- [ ] Update `composer.json`: replace `elastic-scout-driver-plus` with `es-scout-driver`
- [ ] Update `composer.json`: update `laravel-query-wizard` to dev-master

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
- [ ] Add `getType(): string` method to custom filters/sorts/includes
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
