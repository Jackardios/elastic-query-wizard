# Elastic Query Wizard

[![Tests](https://github.com/Jackardios/elastic-query-wizard/actions/workflows/tests.yml/badge.svg)](https://github.com/Jackardios/elastic-query-wizard/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-8892BF.svg)](https://www.php.net/)

A powerful Laravel package for building Elasticsearch queries with JSON:API style filtering, sorting, and relation loading. Built on top of [laravel-query-wizard](https://github.com/Jackardios/laravel-query-wizard) and [es-scout-driver](https://github.com/Jackardios/es-scout-driver).

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Documentation](#documentation)
- [Usage Examples](#usage-examples)
- [License](#license)

## Features

- **Declarative API** — define allowed filters, sorts, and includes in one place
- **Security** — only explicitly allowed parameters are applied to queries
- **Full-text Search** — match, multi_match, fuzzy and other Elasticsearch query types
- **Geo Queries** — filter and sort by geographic coordinates
- **Flexible Configuration** — pass additional parameters to any query
- **Eloquent Integration** — load relations and accessors after Elasticsearch query execution
- **JSON:API Compatible** — standardized query parameter format

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Elasticsearch 8.x
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
// GET /posts?fields[posts]=id,title,status

ElasticQueryWizard::for(Post::class)
    ->allowedFields(['id', 'title', 'status', 'body', 'created_at'])
    ->build()
    ->execute()
    ->models();
```

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
| Fields | `fields[resource]=field1,field2` | `?fields[posts]=id,title` |
| Appends | `append=accessor1,accessor2` | `?append=full_name` |

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) file for details.
