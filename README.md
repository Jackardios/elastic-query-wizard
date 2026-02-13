# Elastic Query Wizard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jackardios/elastic-query-wizard.svg)](https://packagist.org/packages/jackardios/elastic-query-wizard)
[![License](https://img.shields.io/packagist/l/jackardios/elastic-query-wizard.svg)](https://packagist.org/packages/jackardios/elastic-query-wizard)                                                            
[![CI](https://github.com/jackardios/elastic-query-wizard/actions/workflows/ci.yml/badge.svg)](https://github.com/jackardios/elastic-query-wizard/actions)

A powerful Laravel package for building Elasticsearch queries with JSON:API style filtering, sorting, and relation loading. Built on top of [laravel-query-wizard](https://github.com/Jackardios/laravel-query-wizard) and [es-scout-driver](https://github.com/Jackardios/es-scout-driver).

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Documentation](#documentation)
- [Usage Examples](#usage-examples)
- [Elasticsearch Version Compatibility](#elasticsearch-version-compatibility)
- [License](#license)

## Features

- **Declarative API** — define allowed filters, sorts, and includes in one place
- **Security** — only explicitly allowed parameters are applied to queries
- **Full-text Search** — match, multi_match, fuzzy and other Elasticsearch query types
- **Geo Queries** — filter and sort by geographic coordinates
- **Flexible Configuration** — pass additional parameters to any query
- **SearchBuilder DSL Integration** — configure `query`, bool clauses, aggregations, highlight, suggest, KNN, and more directly on the wizard
- **Consistent DSL Facades** — use `ElasticQuery` and `ElasticAggregation` as package-local proxies for full `es-scout-driver` capabilities
- **Eloquent Integration** — load relations and accessors after Elasticsearch query execution
- **JSON:API Compatible** — standardized query parameter format

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Elasticsearch 8.x or 9.x
- [es-scout-driver](https://github.com/Jackardios/es-scout-driver)
- [laravel-query-wizard](https://github.com/Jackardios/laravel-query-wizard)

## Installation

```bash
composer require jackardios/elastic-query-wizard
```

Make sure your model uses the `Searchable` trait from `es-scout-driver`:

```php
use Jackardios\EsScoutDriver\Searchable;

class Post extends Model
{
    use Searchable;

    // ...
}
```

## Quick Start

### Basic Example

```php
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticSort;

// GET /posts?filter[status]=published&filter[title]=laravel&sort=-created_at&include=author

$posts = ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
        ElasticFilter::match('title'),
        ElasticFilter::range('created_at'),
    ])
    ->allowedSorts([
        ElasticSort::field('created_at'),
        ElasticSort::field('title'),
    ])
    ->allowedIncludes(['author', 'comments'])
    ->build()
    ->execute()
    ->models();
```

### Advanced SearchBuilder DSL Example

```php
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticAggregation;

$results = ElasticQueryWizard::for(Article::class)
    ->allowedFilters([
        ElasticFilter::term('status'),
        ElasticFilter::multiMatch(['title^3', 'body'], 'search'),
    ])
    ->query(ElasticQuery::match('language', 'en'))
    ->must(ElasticQuery::range('published_at')->gte('2024-01-01'))
    ->highlight('title')
    ->aggregate('by_author', ElasticAggregation::terms('author')->size(10))
    ->trackTotalHits(true)
    ->build()
    ->execute();
```

### Geo Filtering Example

```php
// GET /places?filter[nearby][lat]=55.75&filter[nearby][lon]=37.62&filter[nearby][distance]=10km

$places = ElasticQueryWizard::for(Place::class)
    ->allowedFilters([
        ElasticFilter::geoDistance('location', 'nearby'),
        ElasticFilter::term('category'),
    ])
    ->allowedSorts([
        ElasticSort::geoDistance('location', 55.75, 37.62, 'distance'),
    ])
    ->build()
    ->execute()
    ->models();
```

### Full-text Search Example

```php
// GET /articles?filter[search]=elasticsearch tutorial&filter[category]=tech

$articles = ElasticQueryWizard::for(Article::class)
    ->allowedFilters([
        ElasticFilter::multiMatch(['title^2', 'body', 'tags'], 'search')
            ->withParameters([
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',
            ]),
        ElasticFilter::term('category'),
    ])
    ->defaultSorts('-created_at')
    ->build()
    ->execute()
    ->models();
```

## Documentation

Detailed documentation for each section:

| Section | Description |
|---------|-------------|
| [Filters](docs/filters.md) | All filter types: term, match, range, geo, fuzzy, and more |
| [Sorts](docs/sorts.md) | Sorting by fields, geography, and scripts |
| [Includes](docs/includes.md) | Loading Eloquent relations after Elasticsearch query |
| [Advanced Usage](docs/advanced.md) | Custom filters, aggregations, working with SearchBuilder |

## Usage Examples

### Date Range Filtering

```php
// GET /orders?filter[created_at][gte]=2024-01-01&filter[created_at][lte]=2024-12-31

ElasticQueryWizard::for(Order::class)
    ->allowedFilters([
        ElasticFilter::range('created_at'),
        ElasticFilter::term('status'),
    ])
    ->build()
    ->execute()
    ->models();
```

### Autocomplete Search (Prefix)

```php
// GET /users?filter[username]=joh

ElasticQueryWizard::for(User::class)
    ->allowedFilters([
        ElasticFilter::prefix('username'),
    ])
    ->build()
    ->execute()
    ->models();
```

### Typo-tolerant Search (Fuzzy)

```php
// GET /products?filter[name]=iphon (will find "iphone")

ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        ElasticFilter::fuzzy('name')->withParameters([
            'fuzziness' => 'AUTO',
        ]),
    ])
    ->build()
    ->execute()
    ->models();
```

### Field Selection

```php
// GET /posts?fields[post]=id,title,status

ElasticQueryWizard::for(Post::class)
    ->allowedFields(['id', 'title', 'status', 'body', 'created_at'])
    ->build()
    ->execute()
    ->models();
```

> By default, `resource` is the model class basename in camelCase (for `Post` it is `post`).
> If you use `forSchema()`, it uses schema `type()`.

### Using Aliases

Aliases allow you to use different parameter names in your API:

```php
// GET /products?filter[tag]=electronics&sort=-date

ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        // Internal field: category, API parameter: tag
        ElasticFilter::term('category', 'tag'),
    ])
    ->allowedSorts([
        // Internal field: created_at, API parameter: date
        ElasticSort::field('created_at', 'date'),
    ])
    ->build()
    ->execute()
    ->models();
```

### Default Sorting

```php
ElasticQueryWizard::for(Post::class)
    ->allowedSorts(['created_at', 'title', 'views'])
    ->defaultSorts('-created_at') // Default: newest first
    ->build()
    ->execute()
    ->models();
```

### Working with Soft Deletes

```php
// GET /posts?filter[trashed]=with (include trashed)
// GET /posts?filter[trashed]=only (only trashed)
// GET /posts?filter[trashed]=without (exclude trashed)
// GET /posts?filter[trashed]=true|false (aliases)

ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::trashed(),
    ])
    ->build()
    ->execute()
    ->models();
```

## Query Parameter Format

The package uses a standardized JSON:API style parameter format:

| Parameter | Format | Example |
|-----------|--------|---------|
| Filters | `filter[field]=value` | `?filter[status]=active` |
| Sorting | `sort=field` or `sort=-field` | `?sort=-created_at` |
| Includes | `include=relation1,relation2` | `?include=author,comments` |
| Fields | `fields[resource]=field1,field2` | `?fields[post]=id,title` |
| Appends | `append=accessor1,accessor2` | `?append=full_name` |

## Elasticsearch Version Compatibility

This package supports Elasticsearch 8.x and 9.x. However, there are important differences between versions.

### Elasticsearch 9.x Breaking Changes

If you're using or upgrading to Elasticsearch 9.x, be aware of the following:

| Feature | Status | Notes |
|---------|--------|-------|
| Range query `from`/`to` params | Removed | Use `gt`/`gte`/`lt`/`lte` instead (this package already uses correct params) |
| `_knn_search` endpoint | Removed | Use `knn` within `_search` endpoint |
| `force_source` highlighting | Removed | Don't pass this parameter via `tapSearchBuilder()` |
| Boolean histogram aggregation | Removed | Use `terms` aggregation for boolean fields |
| `random_score` default field | Changed | Default changed from `_id` to `_seq_no`; specify field explicitly for consistent behavior |
| Frozen indices | Removed | Unfreeze indices before upgrading to ES 9.x |

### Safe Usage Examples

```php
// Range filter - uses correct ES 9.x compatible operators
ElasticFilter::range('price')  // Accepts: gt, gte, lt, lte

// For random scoring, use ElasticSort::random() or tapSearchBuilder with Query::functionScore()
// Option 1: Use ElasticSort::random()
->allowedSorts(ElasticSort::random('random')->seed(12345)->field('_seq_no'))

// Option 2: Use tapSearchBuilder with Query::functionScore()
->tapSearchBuilder(function ($builder) {
    $builder->must(
        Query::functionScore()
            ->addFunction([
                'random_score' => [
                    'seed' => 12345,
                    'field' => '_seq_no',  // Explicit field for consistent behavior across ES versions
                ],
            ])
            ->boostMode('replace')
    );
})

// For boolean aggregations, use terms instead of histogram
->aggregate('by_status', ElasticAggregation::terms('is_active'))
```

## Testing

```bash
make test
```

## License

MIT License. See [LICENSE](LICENSE) file for details.
