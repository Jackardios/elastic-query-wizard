# Sorts

Sorts determine the order of Elasticsearch query results. All sorts are created through the `ElasticSort` factory class.

## Table of Contents

- [General Principles](#general-principles)
- [Field Sort](#field-sort)
- [Geo Distance Sort](#geo-distance-sort)
- [Script Sort](#script-sort)
- [Callback Sort](#callback-sort)
- [Default Sorting](#default-sorting)
- [Aliases](#aliases)

## General Principles

### Registering Sorts

```php
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticSort;

ElasticQueryWizard::for(Post::class)
    ->allowedSorts([
        ElasticSort::field('created_at'),
        ElasticSort::field('title'),
        ElasticSort::field('views'),
    ])
    ->build();
```

### Query Parameter Format

```
# Ascending
GET /posts?sort=created_at

# Descending (- prefix)
GET /posts?sort=-created_at

# Multiple sorts (comma-separated)
GET /posts?sort=-created_at,title
```

### Security

Only explicitly allowed sorts will be applied.
By default, unknown sorts trigger `InvalidSortQuery`.
If you disable this exception in config, unknown sorts are ignored:

```php
->allowedSorts([
    ElasticSort::field('created_at'),
])

// GET /posts?sort=secret_field
// By default: throws InvalidSortQuery
// With disable_invalid_sort_query_exception=true: ignored
```

---

## Field Sort

Basic sorting by field value.

### Usage

```php
ElasticSort::field('created_at')
ElasticSort::field('price')
ElasticSort::field('title.keyword')  // For text fields with keyword subfield
```

### Query Parameters

```
# Ascending (asc)
GET /products?sort=price

# Descending (desc)
GET /products?sort=-price
```

### Elasticsearch Query

```json
{
  "sort": [
    { "price": "asc" }
  ]
}
```

### Sorting Text Fields

Text fields cannot be sorted directly. Use the keyword subfield:

```php
// If mapping has title.keyword
ElasticSort::field('title.keyword', 'title')
```

### Advanced Field Sort Options

`ElasticSort::field()` supports all field-sort options available in `es-scout-driver`:

```php
ElasticSort::field('price')
    ->missingLast()
    ->mode('avg')
    ->unmappedType('long')
    ->nested(['path' => 'offers'])
    ->numericType('double')
    ->format('strict_date_optional_time')
```

| Method | Description |
|--------|-------------|
| `missing(string|int|float|bool)` | Explicit value for missing docs (`_first`, `_last`, or scalar) |
| `missingFirst()` | Shortcut for `missing('_first')` |
| `missingLast()` | Shortcut for `missing('_last')` |
| `mode(string)` | Mode for multi-valued fields (`min`, `max`, `avg`, `sum`, `median`) |
| `unmappedType(string)` | Fallback type when field is unmapped |
| `nested(array)` | Nested sort context |
| `numericType(string)` | Force numeric sort type (`double`, `long`, etc.) |
| `format(string)` | Date format for date sort values |

---

## Geo Distance Sort

Sort by distance from a geographic point.

### Usage

```php
ElasticSort::geoDistance(
    property: 'location',    // Name of the geo_point field
    lat: 55.75,             // Latitude of the center point
    lon: 37.62,             // Longitude of the center point
    alias: 'distance'       // Parameter name in API
)
```

### Query Parameters

```
# Closest first
GET /places?sort=distance

# Farthest first
GET /places?sort=-distance
```

### Elasticsearch Query

```json
{
  "sort": [
    {
      "_geo_distance": {
        "location": { "lat": 55.75, "lon": 37.62 },
        "order": "asc",
        "unit": "km"
      }
    }
  ]
}
```

### Additional Parameters

```php
ElasticSort::geoDistance('location', 55.75, 37.62, 'distance')
    ->unit('mi')              // Distance unit
    ->mode('avg')             // Mode for arrays of points
    ->distanceType('arc')     // Distance calculation type
    ->ignoreUnmapped()        // Ignore documents without field
```

### Configuration Methods

| Method | Description | Values |
|--------|-------------|--------|
| `unit(string)` | Distance unit | `m`, `km`, `mi`, `yd`, `ft` |
| `mode(string)` | Mode for arrays | `min`, `max`, `avg`, `median` |
| `distanceType(string)` | Calculation type | `arc` (accurate), `plane` (fast) |
| `ignoreUnmapped(bool)` | Ignore missing fields | `true`, `false` |

### Example with Settings

```php
ElasticSort::geoDistance('location', 55.75, 37.62, 'distance')
    ->unit('km')
    ->mode('min')           // For array of points, take minimum distance
    ->distanceType('plane') // Faster calculation
    ->ignoreUnmapped()      // Don't fail if field is missing
```

---

## Script Sort

Sorting based on computed values using Painless scripts.

### Usage

```php
ElasticSort::script(
    scriptSource: "doc['price'].value * params.factor",
    property: 'weighted_price',  // Internal name
    alias: 'custom'              // Parameter name in API
)
```

### Query Parameters

```
GET /products?sort=custom
GET /products?sort=-custom
```

### Elasticsearch Query

```json
{
  "sort": [
    {
      "_script": {
        "type": "number",
        "script": {
          "source": "doc['price'].value * params.factor",
          "params": { "factor": 1.2 }
        },
        "order": "asc"
      }
    }
  ]
}
```

### Configuration Methods

| Method | Description | Default |
|--------|-------------|---------|
| `type(string)` | Return value type | `number` |
| `params(array)` | Script parameters | `[]` |
| `mode(string)` | Mode for arrays | `null` |
| `nested(array)` | Nested sort context | `null` |

### Examples

#### Sort by Computed Price

```php
ElasticSort::script("doc['price'].value * params.discount", 'discounted_price')
    ->params(['discount' => 0.9])
    ->type('number')
```

#### Case-insensitive String Sorting

```php
ElasticSort::script("doc['title.keyword'].value.toLowerCase()", 'title_lower')
    ->type('string')
```

#### Sort by Distance with Bonus

```php
ElasticSort::script(
    "def distance = doc['location'].arcDistance(params.lat, params.lon); " .
    "def bonus = doc['is_premium'].value ? 0 : 1000; " .
    "return distance + bonus;",
    'smart_distance'
)
    ->params(['lat' => 55.75, 'lon' => 37.62])
    ->type('number')
```

#### Sort by Value Presence

```php
ElasticSort::script(
    "doc['featured_at'].size() > 0 ? 0 : 1",
    'featured_first'
)
    ->type('number')
```

---

## Callback Sort

Create a custom sort through a callback function.

### Usage

```php
use Jackardios\EsScoutDriver\Search\SearchBuilder;

ElasticSort::callback('relevance', function (SearchBuilder $builder, string $direction) {
    $builder->sort('_score', $direction);
})
```

### Callback Signature

```php
function (SearchBuilder $builder, string $direction, string $property): void
```

| Argument | Description |
|----------|-------------|
| `$builder` | SearchBuilder instance |
| `$direction` | Direction: `asc` or `desc` |
| `$property` | Sort property name |

### Examples

#### Sort by Score

```php
ElasticSort::callback('relevance', function ($builder, $direction) {
    $builder->sort('_score', $direction);
})
```

#### Complex Sorting

```php
ElasticSort::callback('priority', function ($builder, $direction) {
    // First by is_featured, then by created_at
    $builder->sort('is_featured', 'desc');
    $builder->sort('created_at', $direction);
})
```

---

## Default Sorting

If the client doesn't specify a sort, you can set a default value:

```php
ElasticQueryWizard::for(Post::class)
    ->allowedSorts([
        ElasticSort::field('created_at'),
        ElasticSort::field('title'),
    ])
    ->defaultSorts('-created_at')  // Newest first
    ->build();
```

### Multiple Default Sorts

```php
->defaultSorts('-is_featured', '-created_at')
// First featured, then by date
```

### Using Sort Objects

```php
->defaultSorts(
    ElasticSort::field('created_at') // will be applied as desc by default
)
```

---

## Aliases

Each sort can have an alias for use in the API:

```php
// Field in ES: created_at
// API parameter: date
ElasticSort::field('created_at', 'date')
```

```
GET /posts?sort=-date
# Will sort by created_at desc
```

This is useful for:
- Hiding internal data structure
- Creating more convenient parameter names
- Combining complex sorts under a simple name
