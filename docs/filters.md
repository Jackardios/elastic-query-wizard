# Filters

Filters allow you to limit Elasticsearch query results based on query parameters. All filters are created through the `ElasticFilter` factory class.

## Table of Contents

- [General Principles](#general-principles)
- [Term Filter](#term-filter)
- [Match Filter](#match-filter)
- [Range Filter](#range-filter)
- [Exists Filter](#exists-filter)
- [Null Filter](#null-filter)
- [MultiMatch Filter](#multimatch-filter)
- [Wildcard Filter](#wildcard-filter)
- [Prefix Filter](#prefix-filter)
- [Fuzzy Filter](#fuzzy-filter)
- [Ids Filter](#ids-filter)
- [Regexp Filter](#regexp-filter)
- [Match Phrase Filter](#match-phrase-filter)
- [Match Phrase Prefix Filter](#match-phrase-prefix-filter)
- [Query String Filter](#query-string-filter)
- [Simple Query String Filter](#simple-query-string-filter)
- [Geo Distance Filter](#geo-distance-filter)
- [Geo Bounding Box Filter](#geo-bounding-box-filter)
- [Geo Shape Filter](#geo-shape-filter)
- [Nested Filter](#nested-filter)
- [More Like This Filter](#more-like-this-filter)
- [Trashed Filter](#trashed-filter)
- [Date Range Filter](#date-range-filter)
- [Callback Filter](#callback-filter)
- [Passthrough Filter](#passthrough-filter)
- [Additional Parameters](#additional-parameters)
- [Aliases](#aliases)
- [Bool Clause Methods](#bool-clause-methods)
- [Filter Groups](#filter-groups)

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

Only explicitly allowed filters will be applied.
By default, unknown filters trigger `InvalidFilterQuery`.
If you disable this exception in config, unknown filters are ignored:

```php
// Only 'status' filter is allowed
->allowedFilters([
    ElasticFilter::term('status'),
])

// GET /posts?filter[status]=active&filter[secret_field]=value
// By default: throws InvalidFilterQuery
// With disable_invalid_filter_query_exception=true: ignored
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

## Null Filter

Filter by NULL/NOT NULL values. Similar to Exists Filter but with configurable logic direction.

### Usage

```php
ElasticFilter::null('deleted_at', 'is_deleted')
```

### Query Parameters

```
# Field IS NULL (doesn't exist)
GET /posts?filter[is_deleted]=1
GET /posts?filter[is_deleted]=true

# Field IS NOT NULL (exists)
GET /posts?filter[is_deleted]=0
GET /posts?filter[is_deleted]=false
```

### Elasticsearch Query

```json
// filter[is_deleted]=true (field IS NULL)
{ "bool": { "must_not": { "exists": { "field": "deleted_at" } } } }

// filter[is_deleted]=false (field IS NOT NULL)
{ "exists": { "field": "deleted_at" } }
```

### Inverted Logic

By default, a truthy value means "field IS NULL". You can invert this behavior:

```php
// Inverted: truthy means "field exists" (NOT NULL)
ElasticFilter::null('thumbnail', 'has_thumbnail')->withInvertedLogic()
```

```
# With inverted logic:
# filter[has_thumbnail]=1 → field EXISTS (NOT NULL)
# filter[has_thumbnail]=0 → field DOES NOT EXIST (NULL)
```

### Configuration Methods

| Method | Description |
|--------|-------------|
| `withInvertedLogic()` | Invert behavior: truthy → NOT NULL, falsy → NULL |
| `withoutInvertedLogic()` | Reset to default behavior |

### Difference from Exists Filter

| Filter | Truthy value | Falsy value |
|--------|--------------|-------------|
| `ExistsFilter` | Field exists | Field doesn't exist |
| `NullFilter` | Field IS NULL | Field IS NOT NULL |
| `NullFilter` (inverted) | Field IS NOT NULL | Field IS NULL |

Use `NullFilter` when the parameter name suggests "is null" semantics (e.g., `is_deleted`, `is_empty`).
Use `ExistsFilter` when the parameter name suggests "has value" semantics (e.g., `has_thumbnail`, `has_email`).

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

## Ids Filter

Filter documents by ID list.

### Usage

```php
ElasticFilter::ids('_id')
```

### Query Parameters

```
GET /posts?filter[_id]=1,2,3
```

---

## Regexp Filter

Filter by regular expression.

### Usage

```php
ElasticFilter::regexp('slug')
```

### Query Parameters

```
GET /posts?filter[slug]=post-.*
```

---

## Match Phrase Filter

Match an exact phrase in the same word order.

### Usage

```php
ElasticFilter::matchPhrase('title')
```

### Query Parameters

```
GET /posts?filter[title]=exact phrase
```

---

## Match Phrase Prefix Filter

Phrase-prefix search (useful for autocomplete).

### Usage

```php
ElasticFilter::matchPhrasePrefix('title', 'autocomplete')
```

### Query Parameters

```
GET /posts?filter[autocomplete]=laravel que
```

---

## Query String Filter

Raw query-string syntax with operators and field-qualified terms.

### Usage

```php
ElasticFilter::queryString('search')
```

### Query Parameters

```
GET /posts?filter[search]=title:laravel AND status:published
```

---

## Simple Query String Filter

Safer query-string syntax that ignores invalid operators.

### Usage

```php
ElasticFilter::simpleQueryString('search')
```

### Query Parameters

```
GET /posts?filter[search]=laravel +wizard -draft
```

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

Antimeridian is officially supported: keep longitude order as-is.
For dateline-crossing boxes, pass `left > right` (for example `170,-10,-170,10`).

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

## Geo Shape Filter

Filter documents by geographic shape relationships.

### Usage

```php
ElasticFilter::geoShape('boundary')
ElasticFilter::geoShape('coverage_area', 'area')
```

### Query Parameters

```
# Envelope (bounding box)
GET /areas?filter[boundary][type]=envelope&filter[boundary][coordinates][0][0]=-10&filter[boundary][coordinates][0][1]=10&filter[boundary][coordinates][1][0]=10&filter[boundary][coordinates][1][1]=-10

# Point
GET /areas?filter[boundary][type]=point&filter[boundary][coordinates][0]=37.62&filter[boundary][coordinates][1]=55.75

# Indexed shape (reference to another document)
GET /areas?filter[boundary][type]=indexed_shape&filter[boundary][index]=shapes&filter[boundary][id]=region_123
```

### Supported Shape Types

| Type | Description |
|------|-------------|
| `envelope` | Bounding box defined by two corner points |
| `polygon` | Closed polygon defined by coordinate array |
| `point` | Single geographic point |
| `indexed_shape` | Reference to a shape stored in another document |

> **Note:** Circle type is not supported as an inline shape in geo_shape queries (ES 8.x/9.x). For radius-based filtering, use [Geo Distance Filter](#geo-distance-filter) instead.

### Elasticsearch Query

```json
{
  "geo_shape": {
    "boundary": {
      "shape": {
        "type": "envelope",
        "coordinates": [[-10.0, 10.0], [10.0, -10.0]]
      }
    }
  }
}
```

### With Options

```php
ElasticFilter::geoShape('boundary')
    ->relation('intersects')  // Spatial relation: intersects, within, contains, disjoint
    ->ignoreUnmapped()        // Ignore if field is unmapped
```

### Spatial Relations

| Relation | Description |
|----------|-------------|
| `intersects` | Shape intersects with the document shape (default) |
| `within` | Document shape is completely within the query shape |
| `contains` | Document shape completely contains the query shape |
| `disjoint` | Shapes do not touch or overlap |

---

## Nested Filter

Filter by fields within nested documents.

### Usage

```php
ElasticFilter::nested('comments', 'author')
ElasticFilter::nested('variants', 'sku', 'variant_sku')
```

### Query Parameters

```
GET /posts?filter[author]=john
GET /posts?filter[variant_sku]=ABC123,DEF456
```

### Elasticsearch Query

```json
{
  "nested": {
    "path": "comments",
    "query": {
      "term": { "comments.author": "john" }
    }
  }
}
```

### With Options

```php
ElasticFilter::nested('comments', 'author')
    ->scoreMode('avg')     // Score mode: avg, max, min, sum, none
    ->ignoreUnmapped()     // Ignore if path is unmapped
```

### With Custom Inner Query

```php
use Jackardios\EsScoutDriver\Support\Query;

// Custom inner query with closure (receives filter value)
ElasticFilter::nested('offers', 'discount')
    ->innerQuery(fn($value) => Query::bool()
        ->must(Query::range('offers.discount')->gte($value))
        ->must(Query::term('offers.active', true))
    )

// Static inner query (ignores filter value)
ElasticFilter::nested('comments', 'active')
    ->innerQuery(Query::term('comments.active', true))
```

### Score Modes

| Mode | Description |
|------|-------------|
| `avg` | Average score of all matching nested documents |
| `max` | Highest score of any matching nested document |
| `min` | Lowest score of any matching nested document |
| `sum` | Sum of all matching nested document scores |
| `none` | Do not use scores |

---

## More Like This Filter

Find documents similar to provided text or documents.

### Usage

```php
// Search across title and body fields for similar content
ElasticFilter::moreLikeThis(['title', 'body'], 'similar')
```

### Query Parameters

```
# Text-based similarity
GET /articles?filter[similar]=elasticsearch distributed search

# Document reference
GET /articles?filter[similar][_index]=articles&filter[similar][_id]=123
```

### Elasticsearch Query

```json
{
  "more_like_this": {
    "fields": ["title", "body"],
    "like": "elasticsearch distributed search"
  }
}
```

### With Options

```php
ElasticFilter::moreLikeThis(['title', 'body'], 'similar')
    ->minTermFreq(2)           // Min term frequency in source doc
    ->maxQueryTerms(25)        // Max query terms to select
    ->minDocFreq(5)            // Min document frequency for terms
    ->maxDocFreq(1000)         // Max document frequency for terms
    ->minWordLength(3)         // Min word length for terms
    ->maxWordLength(20)        // Max word length for terms
    ->analyzer('english')       // Analyzer for query text
    ->minimumShouldMatch('30%') // Min terms that should match
    ->boost(1.5)               // Query boost factor
    ->include(false)           // Include input docs in results
    ->boostTerms(2.0)          // Boost factor for significant terms
```

### Configuration Parameters

| Parameter | Description |
|-----------|-------------|
| `minTermFreq` | Minimum frequency of a term in the source document to be considered |
| `maxQueryTerms` | Maximum number of query terms selected |
| `minDocFreq` | Minimum document frequency for a term to be considered |
| `maxDocFreq` | Maximum document frequency for a term (excludes common words) |
| `minWordLength` | Minimum length of a word to be considered |
| `maxWordLength` | Maximum length of a word to be considered |
| `analyzer` | Analyzer to use for the query text |
| `minimumShouldMatch` | Minimum number of terms that should match (number or percentage) |
| `boost` | Boost factor for the query |
| `include` | Whether to include the input documents in the results |
| `boostTerms` | Boost factor for terms considered significant |

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
GET /posts?filter[trashed]=true
GET /posts?filter[trashed]=1

# Only deleted
GET /posts?filter[trashed]=only

# Explicitly exclude deleted
GET /posts?filter[trashed]=without
GET /posts?filter[trashed]=false
GET /posts?filter[trashed]=0
```

### Parameter Values

| Value | Description |
|-------|-------------|
| (not specified) | Only non-deleted records |
| `with`, `true`, `1` | All records, including deleted |
| `only` | Only deleted records |
| `without`, `false`, `0` | Only non-deleted records |

### Important Limitation

`TrashedFilter` is a root-level filter and cannot be used inside filter groups.

---

## Date Range Filter

Specialized range filter for date fields with custom from/to keys. Unlike `RangeFilter` which uses `gt/gte/lt/lte`, this filter uses configurable keys (default: `from`/`to`).

### Usage

```php
ElasticFilter::dateRange('created_at')
```

### Query Parameters

```
# Date range with from/to
GET /orders?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31

# Only "from" bound
GET /orders?filter[created_at][from]=2024-01-01

# Only "to" bound
GET /orders?filter[created_at][to]=2024-12-31
```

### Elasticsearch Query

```json
{
  "range": {
    "created_at": {
      "gte": "2024-01-01",
      "lte": "2024-12-31"
    }
  }
}
```

### Configuration Methods

```php
ElasticFilter::dateRange('created_at')
    ->fromKey('start')           // Change "from" key to "start"
    ->toKey('end')               // Change "to" key to "end"
    ->dateFormat('yyyy-MM-dd')   // Set date format
    ->timezone('+03:00')         // Set timezone
```

| Method | Description |
|--------|-------------|
| `fromKey(string)` | Change the key for the lower bound (default: `from`) |
| `toKey(string)` | Change the key for the upper bound (default: `to`) |
| `dateFormat(string)` | Set the date format for Elasticsearch |
| `timezone(string)` | Set the timezone for date parsing |

### With Custom Keys

```php
// Use start/end instead of from/to
ElasticFilter::dateRange('published_at', 'period')
    ->fromKey('start')
    ->toKey('end')
```

```
GET /posts?filter[period][start]=2024-01-01&filter[period][end]=2024-06-30
```

### When to Use

Use `DateRangeFilter` when:
- You want `from`/`to` style parameters instead of `gte`/`lte`
- You need custom key names for date bounds
- You want built-in date format and timezone support

Use `RangeFilter` when:
- You need standard Elasticsearch operators (`gt`, `gte`, `lt`, `lte`)
- You're working with numeric values, not dates

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
- Stable external API naming across schema changes

---

## Bool Clause Methods

By default, each filter is added to a specific bool clause (e.g., `filter` for term queries, `must` for match queries). You can override this behavior using clause methods.

### Available Methods

| Method | Description |
|--------|-------------|
| `inFilter()` | Add to `filter` clause (no scoring, cached) |
| `inMust()` | Add to `must` clause (affects scoring) |
| `inShould()` | Add to `should` clause (optional matching) |
| `inMustNot()` | Add to `must_not` clause (exclusion) |

### Default Clauses by Filter Type

| Filter Type | Default Clause |
|-------------|----------------|
| TermFilter, RangeFilter, ExistsFilter, etc. | `filter` |
| MatchFilter, MultiMatchFilter, FuzzyFilter, etc. | `must` |

### Usage

```php
// Override default clause
ElasticFilter::term('status')->inMust()    // Now affects scoring
ElasticFilter::match('title')->inFilter()  // Now cached, no scoring

// Exclusion
ElasticFilter::term('status')->inMustNot() // Exclude documents

// Optional matching (OR logic with minimum_should_match)
ElasticFilter::term('tag')->inShould()
```

### Elasticsearch Query

```php
ElasticFilter::term('status')->inShould()
```

```json
{
  "bool": {
    "should": [
      { "term": { "status": "active" } }
    ]
  }
}
```

---

## Filter Groups

Filter groups allow you to create complex nested query structures. Groups contain child filters and wrap them in a bool or nested query.

### Available Group Types

| Type | Description |
|------|-------------|
| `ElasticGroup::bool()` | Groups filters into a bool query |
| `ElasticGroup::nested()` | Groups filters into a nested query for nested documents |

### Bool Group

Groups filters into a bool query with optional `minimum_should_match`.

```php
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\ElasticFilter;

ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('category'),

        // OR condition: match at least one of status OR priority
        ElasticGroup::bool('advanced')
            ->minimumShouldMatch(1)
            ->inFilter()
            ->children([
                ElasticFilter::term('status', 'status')->inShould(),
                ElasticFilter::term('priority', 'priority')->inShould(),
            ]),
    ])
    ->build();
```

#### Query Parameters

```
GET /posts?filter[category]=tech&filter[status]=active&filter[priority]=high
```

#### Elasticsearch Query

```json
{
  "bool": {
    "filter": [
      { "term": { "category": "tech" } },
      {
        "bool": {
          "should": [
            { "term": { "status": "active" } },
            { "term": { "priority": "high" } }
          ],
          "minimum_should_match": 1
        }
      }
    ]
  }
}
```

### Nested Group

Groups filters into a nested query for filtering on nested document fields.

```php
ElasticGroup::nested('comments')
    ->inFilter()
    ->children([
        ElasticFilter::term('comments.author', 'author'),
        ElasticFilter::match('comments.body', 'comment_search')->inMust(),
    ])
```

#### Query Parameters

Child filter names are used directly in URL (not the nested path):

```
GET /posts?filter[author]=john&filter[comment_search]=great
```

#### Elasticsearch Query

```json
{
  "bool": {
    "filter": [
      {
        "nested": {
          "path": "comments",
          "query": {
            "bool": {
              "filter": [
                { "term": { "comments.author": "john" } }
              ],
              "must": [
                { "match": { "comments.body": "great" } }
              ]
            }
          }
        }
      }
    ]
  }
}
```

### Nested Group Options

```php
ElasticGroup::nested('offers')
    ->scoreMode('avg')      // Score mode: avg, max, min, sum, none
    ->ignoreUnmapped()      // Ignore if path is unmapped
    ->inFilter()
    ->children([...])
```

### Nested Groups (Groups inside Groups)

Groups can be nested within each other:

```php
ElasticGroup::bool('complex')
    ->inFilter()
    ->children([
        ElasticGroup::nested('variants')
            ->inFilter()
            ->children([
                ElasticFilter::term('variants.sku', 'sku'),
                ElasticFilter::range('variants.price', 'price'),
            ]),
        ElasticFilter::term('status', 'status'),
    ])
```

### Important Notes

1. **URL Syntax**: Group names are NOT used in URL. Use child filter aliases directly:
   ```
   // Correct
   GET /posts?filter[status]=active&filter[priority]=high

   // Wrong - group name 'advanced' is not a valid filter key
   GET /posts?filter[advanced]=something
   ```

2. **Dot Notation Fields**: For nested fields, use dot notation in field name and simple alias:
   ```php
   ElasticFilter::term('comments.author_id', 'author')  // Field: comments.author_id, Alias: author
   ```

3. **Deduplication**: If the same filter name appears both at root level and inside a group, only the group version is applied

4. **Unsupported in Groups**: `ElasticFilter::trashed()`, `ElasticFilter::callback()`, and `ElasticFilter::passthrough()` cannot be used inside groups
