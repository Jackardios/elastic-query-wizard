# Filters

Filters allow you to limit Elasticsearch query results based on query parameters. All filters are created through the `ElasticFilter` factory class.

## Table of Contents

- [General Principles](#general-principles)
- [Term Filter](#term-filter)
- [Match Filter](#match-filter)
- [Range Filter](#range-filter)
- [Exists Filter](#exists-filter)
- [MultiMatch Filter](#multimatch-filter)
- [Wildcard Filter](#wildcard-filter)
- [Prefix Filter](#prefix-filter)
- [Fuzzy Filter](#fuzzy-filter)
- [Geo Distance Filter](#geo-distance-filter)
- [Geo Bounding Box Filter](#geo-bounding-box-filter)
- [Trashed Filter](#trashed-filter)
- [Callback Filter](#callback-filter)
- [Passthrough Filter](#passthrough-filter)
- [Additional Parameters](#additional-parameters)
- [Aliases](#aliases)

## General Principles

### Registering Filters

```php
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticFilter;

ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
        ElasticFilter::match('title'),
        ElasticFilter::range('created_at'),
    ])
    ->build();
```

### Security

Only explicitly allowed filters will be applied. If a client passes an unallowed filter, it will be ignored:

```php
// Only 'status' filter is allowed
->allowedFilters([
    ElasticFilter::term('status'),
])

// GET /posts?filter[status]=active&filter[secret_field]=value
// filter[secret_field] will be ignored
```

---

## Term Filter

Exact match filter for keyword fields. Used for fields that are not analyzed (statuses, identifiers, tags, etc.).

### Usage

```php
ElasticFilter::term('status')
```

### Query Parameters

```
# Single value
GET /posts?filter[status]=published

# Multiple values (OR)
GET /posts?filter[status]=published,draft
```

### Elasticsearch Query

```json
// Single value
{ "term": { "status": "published" } }

// Multiple values
{ "terms": { "status": ["published", "draft"] } }
```

### With Additional Parameters

```php
ElasticFilter::term('status')->withParameters([
    'boost' => 2.0,
    'case_insensitive' => true,
])
```

---

## Match Filter

Full-text search with text analysis. Used for text fields where word-based search is required.

### Usage

```php
ElasticFilter::match('title')
```

### Query Parameters

```
GET /posts?filter[title]=hello world
```

### Elasticsearch Query

```json
{ "match": { "title": "hello world" } }
```

### With Additional Parameters

```php
ElasticFilter::match('title')->withParameters([
    'operator' => 'and',        // All words must be present
    'fuzziness' => 'AUTO',      // Handle typos
    'minimum_should_match' => '75%',
])
```

### Available Parameters

| Parameter | Description |
|-----------|-------------|
| `operator` | `or` (default) or `and` |
| `fuzziness` | `AUTO`, `0`, `1`, `2` — allowed edit distance |
| `prefix_length` | Number of initial characters without fuzzy matching |
| `minimum_should_match` | Minimum number of matching terms |
| `analyzer` | Analyzer for query processing |
| `boost` | Relevance multiplier |

---

## Range Filter

Filter by value range. Suitable for numeric fields, dates, and other ordered types.

### Usage

```php
ElasticFilter::range('price')
ElasticFilter::range('created_at')
```

### Query Parameters

```
# Range "from-to"
GET /products?filter[price][gte]=100&filter[price][lte]=500

# Lower bound only
GET /products?filter[price][gt]=100

# Upper bound only
GET /products?filter[price][lt]=1000

# Date range
GET /posts?filter[created_at][gte]=2024-01-01&filter[created_at][lte]=2024-12-31
```

### Supported Operators

| Operator | Description |
|----------|-------------|
| `gt` | Greater than |
| `gte` | Greater than or equal |
| `lt` | Less than |
| `lte` | Less than or equal |

### Elasticsearch Query

```json
{
  "range": {
    "price": {
      "gte": 100,
      "lte": 500
    }
  }
}
```

### With Additional Parameters

```php
ElasticFilter::range('created_at')->withParameters([
    'format' => 'yyyy-MM-dd',
    'time_zone' => '+03:00',
])
```

---

## Exists Filter

Check for presence or absence of a field value.

### Usage

```php
ElasticFilter::exists('thumbnail')
```

### Query Parameters

```
# Field exists (has a value)
GET /posts?filter[thumbnail]=1
GET /posts?filter[thumbnail]=true

# Field does not exist (null or missing)
GET /posts?filter[thumbnail]=0
GET /posts?filter[thumbnail]=false
```

### Elasticsearch Query

```json
// filter[thumbnail]=1
{ "exists": { "field": "thumbnail" } }

// filter[thumbnail]=0
{ "bool": { "must_not": { "exists": { "field": "thumbnail" } } } }
```

---

## MultiMatch Filter

Search across multiple fields simultaneously. Ideal for implementing site-wide search.

### Usage

```php
// Search across title, body, and tags fields
ElasticFilter::multiMatch(['title', 'body', 'tags'], 'search')

// With boost for specific fields
ElasticFilter::multiMatch(['title^3', 'body^2', 'tags'], 'search')
```

### Query Parameters

```
GET /articles?filter[search]=elasticsearch tutorial
```

### Elasticsearch Query

```json
{
  "multi_match": {
    "query": "elasticsearch tutorial",
    "fields": ["title^3", "body^2", "tags"]
  }
}
```

### With Additional Parameters

```php
ElasticFilter::multiMatch(['title', 'body'], 'search')->withParameters([
    'type' => 'best_fields',      // Search strategy
    'tie_breaker' => 0.3,         // Influence of other fields
    'operator' => 'and',
    'fuzziness' => 'AUTO',
])
```

### Multi_match Types

| Type | Description |
|------|-------------|
| `best_fields` | Uses score from the best matching field (default) |
| `most_fields` | Sums scores from all fields |
| `cross_fields` | Analyzes terms as if they were in a single field |
| `phrase` | Phrase search in each field |
| `phrase_prefix` | Phrase search with prefix for autocomplete |

---

## Wildcard Filter

Pattern matching search using wildcards. Supports `*` (any number of characters) and `?` (single character).

### Usage

```php
ElasticFilter::wildcard('sku')
```

### Query Parameters

```
# Any characters after ABC
GET /products?filter[sku]=ABC*

# Single character at position
GET /products?filter[sku]=AB?123

# Combination
GET /products?filter[sku]=*-2024-?
```

### Elasticsearch Query

```json
{ "wildcard": { "sku": { "value": "ABC*" } } }
```

### With Additional Parameters

```php
ElasticFilter::wildcard('sku')->withParameters([
    'boost' => 1.5,
    'case_insensitive' => true,
])
```

> **Warning:** Wildcard queries can be resource-intensive, especially if the pattern starts with `*`. Use with caution on large indices.

---

## Prefix Filter

Prefix-based search. Optimized for implementing autocomplete.

### Usage

```php
ElasticFilter::prefix('username')
```

### Query Parameters

```
# Users whose names start with "joh"
GET /users?filter[username]=joh
```

### Elasticsearch Query

```json
{ "prefix": { "username": { "value": "joh" } } }
```

### With Additional Parameters

```php
ElasticFilter::prefix('username')->withParameters([
    'case_insensitive' => true,
])
```

---

## Fuzzy Filter

Fuzzy search with typo tolerance. Uses Levenshtein distance to find similar terms.

### Usage

```php
ElasticFilter::fuzzy('name')
```

### Query Parameters

```
# Will find "iphone" when searching "iphon" or "ipohne"
GET /products?filter[name]=iphon
```

### Elasticsearch Query

```json
{ "fuzzy": { "name": { "value": "iphon" } } }
```

### With Additional Parameters

```php
ElasticFilter::fuzzy('name')->withParameters([
    'fuzziness' => 'AUTO',      // Automatic determination
    'max_expansions' => 50,     // Max number of variations
    'prefix_length' => 2,       // First N characters without fuzzy
    'transpositions' => true,   // Consider transpositions (ab -> ba)
])
```

### Fuzziness Values

| Value | Description |
|-------|-------------|
| `0` | Exact match |
| `1` | One edit |
| `2` | Two edits |
| `AUTO` | Automatically based on string length |
| `AUTO:3,6` | 0 for 1-2 chars, 1 for 3-5, 2 for 6+ |

---

## Geo Distance Filter

Filter by distance from a geographic point. For `geo_point` field types.

### Usage

```php
// property — name of the geo_point field
// alias (optional) — parameter name in API
ElasticFilter::geoDistance('location', 'nearby')
```

### Query Parameters

```
GET /places?filter[nearby][lat]=55.75&filter[nearby][lon]=37.62&filter[nearby][distance]=10km
```

### Required Parameters

| Parameter | Description |
|-----------|-------------|
| `lat` | Latitude of the center point |
| `lon` | Longitude of the center point |
| `distance` | Search radius (e.g., `10km`, `5mi`, `1000m`) |

### Elasticsearch Query

```json
{
  "geo_distance": {
    "distance": "10km",
    "location": {
      "lat": 55.75,
      "lon": 37.62
    }
  }
}
```

### Distance Units

| Unit | Description |
|------|-------------|
| `m` | Meters |
| `km` | Kilometers |
| `mi` | Miles |
| `yd` | Yards |
| `ft` | Feet |

---

## Geo Bounding Box Filter

Filter by rectangular area on the map.

### Usage

```php
ElasticFilter::geoBoundingBox('location', 'bbox')
```

### Query Parameters

Format: `[left, bottom, right, top]` (minLon, minLat, maxLon, maxLat)

```
GET /places?filter[bbox][]=36.0&filter[bbox][]=55.0&filter[bbox][]=38.0&filter[bbox][]=56.0
```

### Elasticsearch Query

```json
{
  "geo_bounding_box": {
    "location": {
      "top_left": {
        "lat": 56.0,
        "lon": 36.0
      },
      "bottom_right": {
        "lat": 55.0,
        "lon": 38.0
      }
    }
  }
}
```

---

## Trashed Filter

Special filter for working with soft deletes.

### Usage

```php
ElasticFilter::trashed()
// or with alias
ElasticFilter::trashed('deleted')
```

### Query Parameters

```
# Default: only non-deleted records

# Include deleted
GET /posts?filter[trashed]=with

# Only deleted
GET /posts?filter[trashed]=only
```

### Parameter Values

| Value | Description |
|-------|-------------|
| (not specified) | Only non-deleted records |
| `with` | All records, including deleted |
| `only` | Only deleted records |

---

## Callback Filter

Create a custom filter through a callback function.

### Usage

```php
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

ElasticFilter::callback('phrase', function (SearchBuilder $builder, mixed $value, string $property) {
    $builder->must(Query::matchPhrase('content', $value));
})
```

### Callback Signature

```php
function (SearchBuilder $builder, mixed $value, string $property): void
```

| Argument | Description |
|----------|-------------|
| `$builder` | SearchBuilder instance for adding queries |
| `$value` | Value passed to the filter |
| `$property` | Filter property name |

### Examples

#### Phrase Search

```php
ElasticFilter::callback('phrase', function ($builder, $value, $property) {
    $builder->must(Query::matchPhrase('content', $value));
})
```

#### Complex Condition

```php
ElasticFilter::callback('available', function ($builder, $value, $property) {
    if ($value) {
        $builder->filter(Query::term('status', 'active'));
        $builder->filter(Query::range('stock')->gt(0));
    }
})
```

#### Nested Query

```php
ElasticFilter::callback('comment_author', function ($builder, $value, $property) {
    $builder->filter(
        Query::nested('comments',
            Query::term('comments.author', $value)
        )
    );
})
```

---

## Passthrough Filter

A no-op filter that registers a parameter but performs no action. Useful when you need to allow a parameter for processing elsewhere.

### Usage

```php
ElasticFilter::passthrough('custom_param')
```

---

## Additional Parameters

Most filters support the `withParameters()` method for passing additional parameters to the Elasticsearch query:

```php
ElasticFilter::match('title')->withParameters([
    'operator' => 'and',
    'fuzziness' => 'AUTO',
    'boost' => 2.0,
])
```

Parameter names correspond to the parameters of the respective Elasticsearch queries. The method automatically converts snake_case to camelCase for builder method calls.

---

## Aliases

Each filter can have an alias — a parameter name in the API that differs from the Elasticsearch field name:

```php
// Field in ES: category_id
// API parameter: category
ElasticFilter::term('category_id', 'category')
```

```
GET /products?filter[category]=electronics
# Will search by category_id field
```

This is useful for:
- Hiding internal data structure
- Creating more convenient parameter names
- Backward compatibility when changing schema
