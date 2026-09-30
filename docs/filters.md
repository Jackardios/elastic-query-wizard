# Filters

Filters allow you to limit Elasticsearch query results based on query parameters. All filters are created through the `ElasticFilter` factory class.

## Table of Contents

- [Quick Reference](#quick-reference)
- [General Principles](#general-principles)
- [Key Concepts](#key-concepts)
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
- [Invalid Values](#invalid-values)

## Quick Reference

| Filter | Use Case | Example |
|--------|----------|---------|
| `term` | Exact match (keywords, IDs, statuses) | `ElasticFilter::term('status')` |
| `match` | Full-text search with analysis | `ElasticFilter::match('title')` |
| `range` | Numeric/date ranges | `ElasticFilter::range('price')` |
| `exists` | Field presence check | `ElasticFilter::exists('thumbnail')` |
| `null` | NULL/NOT NULL check | `ElasticFilter::null('deleted_at')` |
| `notNull` | NOT NULL/NULL check | `ElasticFilter::notNull('thumbnail')` |
| `multiMatch` | Search across multiple fields | `ElasticFilter::multiMatch(['title', 'body'], 'q')` |
| `wildcard` | Pattern matching (`*`, `?`) — see [warning](#wildcard-filter) | `ElasticFilter::wildcard('sku')` |
| `prefix` | Prefix-based search (autocomplete) | `ElasticFilter::prefix('username')` |
| `fuzzy` | Typo-tolerant search | `ElasticFilter::fuzzy('name')` |
| `ids` | Filter by document IDs | `ElasticFilter::ids('_id')` |
| `regexp` | Regular expression matching — see [warning](#regexp-filter) | `ElasticFilter::regexp('slug')` |
| `matchPhrase` | Exact phrase match | `ElasticFilter::matchPhrase('title')` |
| `matchPhrasePrefix` | Phrase prefix (autocomplete) | `ElasticFilter::matchPhrasePrefix('title')` |
| `queryString` | Raw query string syntax, trusted input only — see [warning](#query-string-filter) | `ElasticFilter::queryString('body', 'q')` |
| `simpleQueryString` | Safe query string syntax | `ElasticFilter::simpleQueryString('body', 'q')` |
| `geoDistance` | Distance from point | `ElasticFilter::geoDistance('location')` |
| `geoBoundingBox` | Rectangle on map | `ElasticFilter::geoBoundingBox('location')` |
| `geoShape` | Geographic shape queries | `ElasticFilter::geoShape('boundary')` |
| `nested` | Nested document fields | `ElasticFilter::nested('comments', 'author')` |
| `moreLikeThis` | Similar documents | `ElasticFilter::moreLikeThis(['title'], 'similar')` |
| `trashed` | Soft delete handling | `ElasticFilter::trashed()` |
| `dateRange` | Date range with from/to keys | `ElasticFilter::dateRange('created_at')` |
| `callback` | Custom filter logic | `ElasticFilter::callback('custom', fn(...) => ...)` |
| `passthrough` | No-op placeholder | `ElasticFilter::passthrough('param')` |

---

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
// With ignore_unknown.filters = true: ignored
```

Allow-listing decides *which* filters run, not what a caller may put inside one. Three filters pass the raw value
straight into the Elasticsearch DSL and therefore need a second look before you expose them to untrusted callers —
[`queryString`](#query-string-filter) (the value can address fields you never allowed),
[`regexp`](#regexp-filter) and [`wildcard`](#wildcard-filter) (the value can force an index-wide scan).

The text and pattern filters (`prefix`, `wildcard`, `regexp`, `fuzzy`, the match family, `queryString`,
`simpleQueryString` and `moreLikeThis`) take `maxLength(int)`: a longer value returns 400 (`InvalidFilterValue`), and
`maxLength(null)` removes the limit. Three have one by default: `regexp` and `prefix` 1000, the longest pattern
Elasticsearch accepts (see [Regexp Filter](#regexp-filter)), and `fuzzy` 256, since a long fuzzy term costs
Elasticsearch tens of kilobytes of memory per character. A query string is kept short by the web
server's URL limit; with `request_data_source` set to `body` nothing limits it, so set `maxLength()` on the filters
that take free text.

---

## Key Concepts

Before diving into specific filters, it's helpful to understand these core Elasticsearch concepts.

### Term vs Match

| Query Type | Description | Use For |
|------------|-------------|---------|
| **Term** | Exact match, no text analysis | Keywords, IDs, statuses, enums, tags |
| **Match** | Full-text search with analysis | Text content, titles, descriptions |

```php
// Term: finds only exact "published" (case-sensitive by default)
ElasticFilter::term('status')  // ?filter[status]=published

// Match: finds "Laravel", "laravel", "LARAVEL" + related words
ElasticFilter::match('title')  // ?filter[title]=laravel
```

**Rule of thumb:** Use `term` for structured data (e.g., `status`, `category_id`), `match` for free-text fields (e.g., `title`, `body`).

### Value Splitting

laravel-query-wizard splits a request value by the filter separator (`,` by default, `separators.filters` in the config),
so `?filter[status]=published,draft` gives `term` and `ids` a list. The value is split once: with another separator or
`withoutValueSplitting()`, `Smith, John` stays one term.

Filters whose value is one pattern or text don't split it: `prefix`, `wildcard`, `regexp`, `fuzzy`, `match`,
`matchPhrase`, `matchPhrasePrefix`, `multiMatch`, `queryString`, `simpleQueryString` and `moreLikeThis`, so `a{1,3}` and `red, blue`
reach Elasticsearch as sent. A list (`?filter[title][]=red&filter[title][]=blue`) is a 400 for all of them except
`moreLikeThis`, which reads a list as several texts; with `withValueSplitting()` a value containing the separator becomes
a list too.

### Bool Clauses (inFilter / inMust / inShould / inMustNot)

Elasticsearch bool queries have four clause types. Each filter is placed into one of these:

| Clause | Method | Scoring | Caching | Use Case |
|--------|--------|---------|---------|----------|
| **filter** | `inFilter()` | No | Yes | Exact filters (term, range, exists) |
| **must** | `inMust()` | Yes | No | Full-text search (match, fuzzy) |
| **should** | `inShould()` | Yes | No | Optional conditions that raise the score |
| **must_not** | `inMustNot()` | No | Yes | Exclusions |

```php
// Default: term goes to filter (no scoring, cached)
ElasticFilter::term('status')

// Override: put term in must (affects relevance score)
ElasticFilter::term('status')->inMust()

// Exclusion: documents without this value
ElasticFilter::term('status')->inMustNot()

// Optional: a match raises the score
ElasticFilter::term('tag1')->inShould()
ElasticFilter::term('tag2')->inShould()
```

At the root the wizard sets no `minimum_should_match`, so Elasticsearch requires one should clause to match only while
the root bool has no `filter` or `must` clause. Once another filter of the request adds one, the should clauses only
raise the score. For "at least one of these", put the filters in a
[bool group](#bool-group) with `minimumShouldMatch(1)`.

**When to change clause:**
- Use `inMust()` when you want the filter to affect relevance scoring
- Use `inShould()` for optional conditions that raise the score (for OR logic use a bool group)
- Use `inMustNot()` to exclude documents
- Keep `inFilter()` (default for exact filters) for best performance

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
{ "term": { "status": { "value": "published" } } }

// Multiple values
{ "terms": { "status": ["published", "draft"] } }
```

### With Additional Parameters

```php
ElasticFilter::term('status')->withParameters([
    'boost' => 2.0,
])
```

A term filter builds a `terms` query for several values, and `terms` has no `case_insensitive` option, so `withParameters()` refuses it; for case-insensitive matching, index the field as a `keyword` with a lowercase normalizer.

### Numeric Fields

The value is sent as the client wrote it, and Elasticsearch fails the search when text meets a numeric field
(`?filter[id]=abc` on an `integer` field). `asNumber()` reads each value as a decimal number (digits with an optional
sign and fraction, no exponent) and returns 400 (`InvalidFilterValue`) for anything else:

```php
ElasticFilter::term('id')->asNumber()
```

```json
// GET /posts?filter[id]=5,7
{ "terms": { "id": [5, 7] } }
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
{ "match": { "title": { "query": "hello world" } } }
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

An empty bound (`filter[price][gte]=&filter[price][lte]=500`) is skipped, so a form can send every field; a range with only empty bounds applies no condition. Unknown operators still return 400.

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

### Numeric Fields

A bound may be a number or a date, and Elasticsearch fails the search on a date or text bound for a numeric field.
`asNumber()` accepts decimal numbers only and returns 400 (`InvalidRangeValue`) for anything else:

```php
ElasticFilter::range('price')->asNumber()
```

A number outside the field type, such as `gte=3000000000` on an `integer` field, still fails the search: see
[Syntax Elasticsearch Refuses](#syntax-elasticsearch-refuses), or bound it with `prepareValueWith()`.

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
{ "bool": { "must_not": [{ "exists": { "field": "thumbnail" } }] } }
```

The negation follows the clause: with `inShould()` a missing field is one of the alternatives
(`"should": [{ "bool": { "must_not": [{ "exists": … }] } }, …]`), and with `inMustNot()` it keeps the documents that have
the field (`"filter": [{ "exists": … }]`). The Null Filter does the same for "field IS NULL".

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
{ "bool": { "must_not": [{ "exists": { "field": "deleted_at" } }] } }

// filter[is_deleted]=false (field IS NOT NULL)
{ "exists": { "field": "deleted_at" } }
```

### Not Null

`ElasticFilter::notNull()` reverses the meaning: a truthy value means "field exists" (NOT NULL).

```php
ElasticFilter::notNull('thumbnail', 'has_thumbnail')
```

```
# filter[has_thumbnail]=1 → field EXISTS (NOT NULL)
# filter[has_thumbnail]=0 → field DOES NOT EXIST (NULL)
```

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

> **Warning:** The value goes into the Elasticsearch pattern verbatim. A caller can send `*` or `*a*` and force a
> full-index scan, so a leading-wildcard pattern from untrusted input is a denial-of-service vector on a large index.
> Either restrict this filter to trusted callers, or normalize the value with `prepareValueWith()` (for example, strip
> a leading `*`) before it reaches the query.

A pattern with many wildcards (`*a` repeated 300 times) is too complex for Elasticsearch, which fails the search with a
400 ([Syntax Elasticsearch Refuses](#syntax-elasticsearch-refuses)). The threshold depends on the pattern, not its
length, so `maxLength()` does not prevent it; limit the wildcards themselves:

```php
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

ElasticFilter::wildcard('sku')->prepareValueWith(function (mixed $value) {
    if (is_string($value) && strlen($value) - strlen(str_replace(['*', '?'], '', $value)) > 10) {
        throw InvalidFilterValue::make($value, 'sku', 'Expected at most 10 wildcards.');
    }

    return $value;
})
```

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

A prefix longer than 1000 characters, Elasticsearch's default `index.max_regex_length`, returns 400
(`InvalidFilterValue`) instead of failing the search. Call `maxLength()` with the index's own limit if you changed it,
or `maxLength(null)` to leave the check to Elasticsearch.

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

A value longer than 256 characters returns 400 (`InvalidFilterValue`): Elasticsearch spends tens of kilobytes of
memory per character of a fuzzy term, and a few thousand characters trip its circuit breaker. Change the limit with
`maxLength()`, or remove it with `maxLength(null)`.

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

Document ids are text, so any value is a valid id. For models with integer keys, `asNumber()` returns 400
(`InvalidFilterValue`) for a value that is not a decimal number instead of searching for it:

```php
ElasticFilter::ids('_id')->asNumber()
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

> **Warning:** The value is used as the regular expression itself, so the caller controls the pattern. Catastrophic
> patterns (`.*.*.*`, deeply nested repetition) are expensive to evaluate and are a denial-of-service vector on a large
> index. Prefer `prefix` or `match` for untrusted input; if you do expose `regexp`, shorten the limit with
> `maxLength()`, turn off the optional operators and keep the index small:
>
> ```php
> ElasticFilter::regexp('slug')
>     ->maxLength(100)
>     ->withParameters(['flags' => 'NONE', 'max_determinized_states' => 1000])
> ```

A pattern longer than 1000 characters, Elasticsearch's default `index.max_regex_length`, returns 400
(`InvalidFilterValue`) instead of failing the search. Call `maxLength()` with the index's own limit if you changed it,
or `maxLength(null)` to leave the check to Elasticsearch.

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

Raw query-string syntax with operators and field-qualified terms. Use it only for trusted input, such as an admin
panel.

### Usage

```php
// Searches the `body` field; the request parameter is `q`
ElasticFilter::queryString('body', 'q')

// Several fields
ElasticFilter::queryString('body', 'q')->withParameters(['fields' => ['title^2', 'body']])
```

The filter searches its property (`fields: ["body"]`) unless `withParameters()` sets `fields` or `default_field`.

### Query Parameters

```
GET /posts?filter[q]=laravel AND (wizard OR builder)
```

> **Warning:** The value is parsed as Elasticsearch query-string syntax, so the caller is not confined to the fields you
> configured — `other_field:value` queries a different field, and `_exists_:other_field` probes one. Anything the
> document holds but the resource does not expose can be probed this way, and Elasticsearch has no option to turn the
> syntax off. Use `simpleQueryString` for untrusted input: it has no field-qualified terms and ignores invalid operators
> instead of erroring.

A term that starts with `*` or `?` (`*son`, `title:?ob`) scans every term of the field. The filter sets
`allow_leading_wildcard: false` and answers such a value with 400 (`InvalidFilterValue`) instead of letting
Elasticsearch fail the search; `->withParameters(['allow_leading_wildcard' => true])` allows them. A lone `*`, a
wildcard inside a quoted phrase and an open range bound (`[* TO 5]`) are not leading wildcards. Other syntax errors,
such as an unbalanced `(`, still fail in Elasticsearch.

---

## Simple Query String Filter

Safer query-string syntax that ignores invalid operators.

### Usage

```php
// Searches the `body` field; the request parameter is `q`
ElasticFilter::simpleQueryString('body', 'q')

// Several fields
ElasticFilter::simpleQueryString('body', 'q')->withParameters(['fields' => ['title^2', 'body']])
```

The filter searches its property (`fields: ["body"]`) unless `withParameters()` sets `fields`.

### Query Parameters

```
GET /posts?filter[q]=laravel +wizard -draft
```

> **Warning:** A fuzzy term (`word~2`) costs Elasticsearch memory for each term, and a few kilobytes of them trip its
> circuit breaker, which fails the search with a 429 for every request the node serves at that moment. For untrusted
> input, set `maxLength()`, or leave the fuzzy operator out of the flags, so `~2` is read as text:
>
> ```php
> ElasticFilter::simpleQueryString('body', 'q')->withParameters([
>     'flags' => 'AND|OR|NOT|PHRASE|PRECEDENCE|PREFIX|ESCAPE|WHITESPACE',
> ])
> ```

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
| `distance` | Search radius greater than zero, with an optional unit (e.g., `10km`, `5mi`, `1000m`; meters without one) |

Latitude must be within [-90, 90] and longitude within [-180, 180]. Units are case-sensitive, as in Elasticsearch.

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

# Named edges, in any order
GET /places?filter[bbox][top]=56.0&filter[bbox][left]=36.0&filter[bbox][bottom]=55.0&filter[bbox][right]=38.0
```

Named edges must be exactly `left`, `bottom`, `right` and `top`; any other key returns 400.

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
GET /areas?filter[boundary][type]=indexed_shape&filter[boundary][id]=region_123
```

### Supported Shape Types

| Type | Description |
|------|-------------|
| `envelope` | Bounding box defined by two corner points |
| `polygon` | GeoJSON polygon: an outer ring followed by optional holes |
| `point` | Single geographic point |
| `indexed_shape` | Reference to a shape stored in another document; enabled with `indexedShapes()` |

Every point is `[lon, lat]`: exactly two numbers, the longitude within ±180 and the latitude within ±90. An envelope
is its top-left and bottom-right corners, so its first latitude may not be below its second. Anything else returns 400.

Each polygon ring is a list of `[lon, lat]` points. A ring whose last point differs from its first is closed for you, and a ring must have at least four points once closed. Rings after the first are holes: documents inside a hole do not match.

Coordinates must be finite numbers; a value such as `1e999` returns 400.

`indexed_shape` values are refused (400) unless the filter names the index and field that hold the shapes; the client
chooses only the document:

```php
ElasticFilter::geoShape('boundary')->indexedShapes('shapes', 'geometry') // index, field (default `shape`)
```

An `id` of no document, or of a document without the field, fails the search in Elasticsearch with a 400
([Syntax Elasticsearch Refuses](#syntax-elasticsearch-refuses)).

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
      "term": { "comments.author": { "value": "john" } }
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
# Text-based similarity (a comma does not split the text)
GET /articles?filter[similar]=elasticsearch, distributed search

# Several texts
GET /articles?filter[similar][]=elasticsearch&filter[similar][]=distributed search
```

### Document References

A document of the searched index can stand for a text, but only when the filter allows it; otherwise a reference
returns 400 (`InvalidFilterValue`):

```php
ElasticFilter::moreLikeThis(['title', 'body'], 'similar')->allowDocumentReferences()
```

```
# Document of the searched index
GET /articles?filter[similar][_id]=123

# Texts and documents
GET /articles?filter[similar][0]=elasticsearch&filter[similar][1][_id]=123
```

A document reference takes only an `_id`: the document is read from the index being searched, so a client cannot read
documents of another index. Any other key (`_index`, `_routing`, …) returns 400 (`InvalidFilterValue`).

> **Warning:** Elasticsearch reads a referenced document whatever the search's other conditions: a tenant filter,
> visibility or soft deletes do not apply to it. A client that names the `_id` of a document it may not see learns what
> the document contains from the similar documents it gets back. Allow references only when every document of the
> index is visible to every client of the endpoint.

### Elasticsearch Query

```json
{
  "more_like_this": {
    "fields": ["title", "body"],
    "like": "elasticsearch, distributed search"
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

# Only deleted
GET /posts?filter[trashed]=only

# Explicitly exclude deleted
GET /posts?filter[trashed]=without
GET /posts?filter[trashed]=false
```

### Parameter Values

| Value | Description |
|-------|-------------|
| (not specified) | Only non-deleted records |
| `with`, `true` | All records, including deleted |
| `only` | Only deleted records |
| `without`, `false` | Only non-deleted records |

Values are read in any letter case. Any other value, `1` and `0` included, returns 400 (`InvalidFilterValue`).

### Requirements

Scout indexes which models are trashed only with `scout.soft_delete` set to `true` (`SCOUT_SOFT_DELETE=true`), for
models using `SoftDeletes`. Without it the filter has nothing to read, so a request for a mode throws a
`LogicException` instead of returning trashed and live models alike.

### Important Limitation

`TrashedFilter` is a root-level filter and cannot be used inside filter groups.

---

## Date Range Filter

Range filter for date fields with `from`/`to` keys, reading dates like `laravel-query-wizard`'s date range filter.

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

# Date-times, with or without an offset
GET /orders?filter[created_at][from]=2024-01-01T09:00&filter[created_at][to]=2024-01-01T18:00:00%2B03:00
```

### Reading Dates

- Each bound is a date (`Y-m-d`) or an ISO 8601 date-time. Anything else — `abc`, epoch numbers, `01/01/2024`,
  date math such as `now-1d` — returns 400 (`InvalidFilterValue`).
- A bound without an offset is read in the application timezone, or the one set with `timezone()`; a date-time with
  an offset keeps its instant. Clients must send `+` in an offset as `%2B`.
- A date names the whole day: `to=2024-12-31` becomes `lt` the start of 2025-01-01.
- A `DateTimeInterface` works as a `default()` bound.
- A key other than the two bounds, and a bound after the year 9999 once read in the filter's timezone, return 400.

The bounds reach Elasticsearch as ISO 8601 date-times with an offset, read with the `strict_date_optional_time`
format, so the field's own mapping format does not matter.

### Elasticsearch Query

```json
// GET /orders?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31, application timezone UTC
{
  "range": {
    "created_at": {
      "gte": "2024-01-01T00:00:00+00:00",
      "lt": "2025-01-01T00:00:00+00:00",
      "format": "strict_date_optional_time"
    }
  }
}
```

### Configuration Methods

```php
ElasticFilter::dateRange('created_at')
    ->fromKey('start')                  // Change "from" key to "start"
    ->toKey('end')                      // Change "to" key to "end"
    ->timezone('Europe/Moscow')         // Read bounds without an offset in this timezone
    ->esFormat('yyyy-MM-dd')            // Sent as `yyyy-MM-dd||strict_date_optional_time`
```

| Method | Description |
|--------|-------------|
| `fromKey(string)` | Change the key for the lower bound (default: `from`) |
| `toKey(string)` | Change the key for the upper bound (default: `to`) |
| `timezone(string)` | Timezone for bounds without an offset (default: the application's); an unknown one throws `InvalidArgumentException` |
| `esFormat(string)` | Elasticsearch `format` tried on the bounds before `strict_date_optional_time`, which is appended so the ISO 8601 bounds are always read (default: `strict_date_optional_time` alone). It does not change which request values are accepted |
| `dateFormat(string)` | Deprecated alias of `esFormat()` |

`withParameters()` refuses `format`, `time_zone`, `gt`, `gte`, `lt` and `lte` with an `InvalidArgumentException`: the
filter sets them from `esFormat()`, the bounds' offsets and the request.

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

Use `DateRangeFilter` for dates: it reads them in the application timezone and covers whole days. Use `RangeFilter`
for numbers, or to pass dates to Elasticsearch as sent (`gt`, `gte`, `lt`, `lte`).

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

Every filter except geoShape, nested, moreLikeThis, null, trashed, passthrough and callback supports the `withParameters()` method for passing additional parameters to the Elasticsearch query (geoShape, nested and moreLikeThis have their own setters):

```php
ElasticFilter::match('title')->withParameters([
    'operator' => 'and',
    'fuzziness' => 'AUTO',
    'boost' => 2.0,
])
```

Parameter names correspond to the parameters of the respective Elasticsearch queries. The method automatically converts snake_case to camelCase for builder method calls.

`withParameters()` checks each name against the es-scout-driver query the filter builds and throws `InvalidArgumentException` for a name the query has no setter for, so a typo fails when the filter is configured rather than as a 500 on the first request that uses it. The parameters a filter takes are the setters of its query, such as `boost` for exists and prefix. A custom filter that uses the `HasParameters` trait can opt into the same check by returning its query classes from `parameterQueryClasses()`.

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
| match, multiMatch, matchPhrase, matchPhrasePrefix, fuzzy, queryString, simpleQueryString, moreLikeThis | `must` |
| Every other filter (term, range, exists, prefix, wildcard, geo…) | `filter` |

### Usage

```php
// Override default clause
ElasticFilter::term('status')->inMust()    // Now affects scoring
ElasticFilter::match('title')->inFilter()  // Now cached, no scoring

// Exclusion
ElasticFilter::term('status')->inMustNot() // Exclude documents

// Optional matching: raises the score (see the note on inShould() above)
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
      { "term": { "status": { "value": "active" } } }
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

Groups filters into a bool query with optional `minimum_should_match` and `boost`.

```php
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\ElasticFilter;

ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('category'),

        // OR condition: match at least one of status OR priority
        ElasticGroup::bool('advanced')
            ->minimumShouldMatch(1)
            ->boost(1.5)  // Scores only in a must or should clause, not in filter
            ->inFilter()
            ->children([
                ElasticFilter::term('status')->inShould(),
                ElasticFilter::term('priority')->inShould(),
            ]),
    ])
    ->build();
```

#### Bool Group Options

| Method | Description |
|--------|-------------|
| `minimumShouldMatch(int\|string)` | Minimum should clauses to match (e.g., `1`, `'75%'`); left out when the request fills no should child |
| `boost(float)` | Boost factor for relevance scoring |
| `inFilter()` / `inMust()` / `inShould()` / `inMustNot()` | Bool clause for the group |
| `children(array)` | Set child filters |

#### Query Parameters

```
GET /posts?filter[category]=tech&filter[status]=active&filter[priority]=high
```

#### Elasticsearch Query

```json
{
  "bool": {
    "filter": [
      { "term": { "category": { "value": "tech" } } },
      {
        "bool": {
          "should": [
            { "term": { "status": { "value": "active" } } },
            { "term": { "priority": { "value": "high" } } }
          ],
          "minimum_should_match": 1,
          "boost": 1.5
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
                { "term": { "comments.author": { "value": "john" } } }
              ],
              "must": [
                { "match": { "comments.body": { "query": "great" } } }
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
ElasticGroup::nested('comments')
    ->scoreMode('avg')       // Score mode: avg, max, min, sum, none
    ->ignoreUnmapped()       // Ignore if path is unmapped
    ->innerHits([            // Retrieve matching nested documents
        'name' => 'matched_comments',
        'size' => 3,
        'sort' => [['date' => ['order' => 'desc']]],
    ])
    ->inFilter()
    ->children([...])
```

#### Nested Group Methods

| Method | Description |
|--------|-------------|
| `scoreMode(string)` | How nested doc scores affect parent: `avg`, `max`, `min`, `sum`, `none` |
| `ignoreUnmapped(bool)` | Ignore query if nested path is unmapped |
| `innerHits(array)` | Retrieve matching nested documents in the response |
| `inFilter()` / `inMust()` / `inShould()` / `inMustNot()` | Bool clause for the group |
| `children(array)` | Set child filters |

#### inner_hits Options

| Option | Description |
|--------|-------------|
| `name` | Name for the inner_hits result set; defaults to the group's name (its alias, or else its path) |
| `size` | Maximum number of nested docs to return (default: 3) |
| `from` | Offset for pagination |
| `sort` | Sort order for nested documents |
| `highlight` | Highlight matching fields |
| `_source` | Fields to include in the response |

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
        ElasticFilter::term('status'),
    ])
```

Nested groups are resolved recursively and support arbitrary depth.

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

3. **Group Names Are Routing-Only**: Group names are never used as filter value keys. Values are routed only by leaf filter aliases.

4. **Unique Leaf Aliases Required**: Leaf filter aliases inside one group tree must be unique. Duplicate aliases throw `DuplicateGroupChildFilterNameException`.

5. **Root vs Group Deduplication**: If the same filter name appears both at root level and inside a group, only the
   group version is applied. A root version with a `default()` throws `FilterNameConflictException` when the wizard
   builds, since its default would never apply: set the default on the leaf.

6. **Unsupported in Groups**: `ElasticFilter::trashed()`, `ElasticFilter::callback()`, `ElasticFilter::passthrough()` and
   any filter that is not an Elasticsearch filter or group cannot be used inside groups. `children()` throws
   `UnsupportedFilterInGroupException` when the group is configured.

7. **Names Across Groups**: A group can't share its name with another allowed filter or group (a nested group's name
   defaults to its path, so two nested groups on one path need names): the build throws `laravel-query-wizard`'s
   `InvalidArgumentException`. A leaf alias can be in only one group: the build throws `FilterNameConflictException`.
   Before, the other filter was silently dropped or one value applied in both groups.

8. **Defaults and Disallowing**: A schema `defaultFilters()` key names a leaf, as the request does; a key naming the
   group itself throws `InvalidArgumentException`. `disallowedFilters()` takes leaf names too: the leaf's key is
   rejected like any disallowed filter, and its default is dropped.

---

## Invalid Values

A filter value that a filter cannot read returns 400. The exception extends `laravel-query-wizard`'s
`InvalidFilterValue` (error code `invalid_filter_value`), names the filter by its public name (the alias when set) and
carries the value (`$exception->filterValue`) and what was expected (`$exception->reason`):

| Filter | Rejected values | Exception |
|--------|-----------------|-----------|
| `range` | not an array; a key other than `gt`, `gte`, `lt`, `lte`; a legacy key (`from`, `to`, `include_lower`, `include_upper`); a bound that is not a decimal number or an ISO 8601 date (exponents and date math such as `now-1d` included) | `InvalidRangeValue` |
| `geoBoundingBox` | not four finite coordinates; unknown edge names; latitude outside [-90, 90] or longitude outside [-180, 180] | `InvalidGeoBoundingBoxValue` |
| `dateRange` | a bound that is not a date or an ISO 8601 date-time; a bound after the year 9999 | `InvalidFilterValue` |
| `geoDistance` | missing `lat`, `lon` or `distance`; a key other than those; coordinates out of range; a distance that is not a finite positive number with an optional unit | `InvalidGeoDistanceValue` |
| `geoShape` | an unknown type; a key other than `type` and `coordinates` (`type` and `id` for an indexed shape); coordinates that do not form the shape | `InvalidGeoShapeValue` |
| `exists`, `null` | not a boolean (`true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`, in any letter case) | `InvalidFilterValue` |
| `trashed` | not `with`, `only`, `without`, `true` or `false` | `InvalidFilterValue` |
| `term`, `ids` with `asNumber()` | a value that is not a decimal number | `InvalidFilterValue` |
| text and pattern filters, `ids` | a JSON boolean (with `request_data_source` set to `body`); a value longer than `maxLength()` | `InvalidFilterValue` |

A value of the wrong shape for the filter (for example a list for `exists`) is rejected earlier with 400
`InvalidFilterQuery`. Blank values (`null`, whitespace) add no condition, and neither does `,` for a filter that splits
its value; the filters that keep their value whole ([Value Splitting](#value-splitting)) send `,` as the text.
`ignore_unknown.filters` covers unknown filter names only, not unreadable values.

A range bound that is a date is passed on as sent, and Elasticsearch reads it with the field's format, in UTC unless
the query sets `time_zone` (`->withParameters(['time_zone' => '+03:00'])`).

### Syntax Elasticsearch Refuses

`queryString` and `regexp` pass their syntax to Elasticsearch, which parses it. A value it cannot parse, such as an
unclosed parenthesis in a query string or `[` in a regular expression, fails the search with
`Elastic\Elasticsearch\Exception\ClientResponseException` whose code is 400, and Laravel reports it as a 500. To
answer the client with 400, render the exception in `bootstrap/app.php`:

```php
use Elastic\Elasticsearch\Exception\ClientResponseException;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (ClientResponseException $exception) {
        if ($exception->getCode() === 400) {
            return response()->json(['message' => 'The search query is invalid.'], 400);
        }
    });
})
```

The same 400 comes from other values Elasticsearch can only judge against the index:

- a `wildcard` pattern too complex to run;
- an `indexed_shape` `id` of no document, or of one without the shape field;
- a range bound outside a numeric field's type (`gte=3000000000` on an `integer` field);
- text in a `term`, `range` or `ids` filter on a numeric field without `asNumber()`.

A search that trips a circuit breaker (for example many fuzzy terms in `simpleQueryString`) fails with 429 instead; it
is a load problem, not a client mistake, so leave it to the application's usual error handling.

A 400 from Elasticsearch can also mean a mistake in the application, such as a query on a field of the wrong type, so
log it before answering.

