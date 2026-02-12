# Includes

Includes allow you to load Eloquent model relations after executing an Elasticsearch query. This combines the power of Elasticsearch search with the flexibility of Laravel's relation handling.

## Table of Contents

- [How It Works](#how-it-works)
- [Registering Includes](#registering-includes)
- [Relationship Include](#relationship-include)
- [Count Include](#count-include)
- [Callback Include](#callback-include)
- [Aliases](#aliases)
- [Usage Examples](#usage-examples)

## How It Works

1. Elasticsearch executes the search and returns document IDs
2. Eloquent loads models by these IDs
3. Includes are applied to the Eloquent Query Builder (eager loading)
4. Relations are loaded efficiently via `with()` or `withCount()`

```
[Elasticsearch] → [IDs] → [Eloquent] → [Models + Relations]
```

## Registering Includes

```php
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticInclude;

ElasticQueryWizard::for(Post::class)
    ->allowedIncludes([
        'author',                              // Simple string
        'comments',
        ElasticInclude::count('comments'),     // Relation count
        ElasticInclude::relationship('tags'),  // Explicit declaration
    ])
    ->build();
```

### Query Parameters

```
# Single relation
GET /posts?include=author

# Multiple relations
GET /posts?include=author,comments,tagsCount
```

### Security

Only explicitly allowed includes will be loaded.
By default, unknown includes trigger `InvalidIncludeQuery`.
If you disable this exception in config, unknown includes are ignored:

```php
->allowedIncludes(['author', 'comments'])

// GET /posts?include=author,secret_relation
// By default: throws InvalidIncludeQuery
// With disable_invalid_include_query_exception=true: ignored
```

---

## Relationship Include

Load related models via Eloquent `with()`.

### Usage

```php
// Simple string (automatically creates RelationshipInclude)
->allowedIncludes(['author', 'comments', 'tags'])

// Explicit creation
use Jackardios\ElasticQueryWizard\ElasticInclude;

->allowedIncludes([
    ElasticInclude::relationship('author'),
    ElasticInclude::relationship('comments'),
])
```

### Query Parameters

```
GET /posts?include=author,comments
```

### Nested Relations

```php
->allowedIncludes([
    'author',
    'comments.author',           // Nested relation
    'comments.author.profile',   // Deep nesting
])
```

```
GET /posts?include=comments.author
```

### Result

```json
{
  "data": [
    {
      "id": 1,
      "title": "My Post",
      "author": {
        "id": 10,
        "name": "John Doe"
      },
      "comments": [
        {
          "id": 100,
          "body": "Great post!",
          "author": {
            "id": 20,
            "name": "Jane Doe"
          }
        }
      ]
    }
  ]
}
```

---

## Count Include

Count related records without loading them. Uses Eloquent `withCount()`.

### Usage

```php
use Jackardios\ElasticQueryWizard\ElasticInclude;

->allowedIncludes([
    ElasticInclude::count('comments'),
    ElasticInclude::count('likes'),
])
```

### Automatic Detection

By default, if an include name ends with `Count`, a CountInclude is automatically created:

```php
// Equivalent declarations:
->allowedIncludes(['commentsCount'])
->allowedIncludes([ElasticInclude::count('comments', 'commentsCount')])
```

### Query Parameters

```
GET /posts?include=commentsCount,likesCount
```

### Result

```json
{
  "data": [
    {
      "id": 1,
      "title": "My Post",
      "comments_count": 15,
      "likes_count": 42
    }
  ]
}
```

### With Alias

```php
ElasticInclude::count('comments', 'total_comments')
```

```
GET /posts?include=total_comments
```

---

## Callback Include

Create a custom include through a callback function.

### Usage

```php
use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\ElasticInclude;

ElasticInclude::callback('latestComments', function (Builder $builder) {
    $builder->with(['comments' => function ($query) {
        $query->latest()->limit(5);
    }]);
})
```

### Callback Signature

```php
function (mixed $subject): mixed
```

| Argument | Description |
|----------|-------------|
| `$subject` | The query subject (Eloquent Builder in includes context) |

> **Note:** The callback receives the Eloquent Query Builder since includes are applied after Elasticsearch returns results. You can use standard Eloquent methods like `with()`, `withCount()`, etc.

### Examples

#### Load with Condition

```php
ElasticInclude::callback('activeComments', function (Builder $builder) {
    $builder->with(['comments' => function ($query) {
        $query->where('is_approved', true);
    }]);
})
```

#### Load with Sorting and Limit

```php
ElasticInclude::callback('topComments', function (Builder $builder) {
    $builder->with(['comments' => function ($query) {
        $query->orderByDesc('likes_count')->limit(3);
    }]);
})
```

#### Load Multiple Relations

```php
ElasticInclude::callback('full', function (Builder $builder) {
    $builder->with(['author', 'comments', 'tags', 'category']);
})
```

#### Conditional Count

```php
ElasticInclude::callback('approvedCommentsCount', function (Builder $builder) {
    $builder->withCount(['comments' => function ($query) {
        $query->where('is_approved', true);
    }]);
})
```

---

## Aliases

Each include can have an alias for use in the API:

```php
// Relation: author
// API parameter: writer
ElasticInclude::relationship('author', 'writer')
```

```
GET /posts?include=writer
# Will load author relation
```

---

## Usage Examples

### Blog with Author and Comments

```php
ElasticQueryWizard::for(Post::class)
    ->allowedFilters([
        ElasticFilter::match('title'),
        ElasticFilter::term('status'),
    ])
    ->allowedIncludes([
        'author',
        'category',
        'comments',
        'comments.author',
        ElasticInclude::count('comments'),
        ElasticInclude::count('likes'),
    ])
    ->build()
    ->execute()
    ->models();
```

```
GET /posts?filter[status]=published&include=author,commentsCount
```

### E-commerce with Products

```php
ElasticQueryWizard::for(Product::class)
    ->allowedFilters([
        ElasticFilter::term('category'),
        ElasticFilter::range('price'),
    ])
    ->allowedIncludes([
        'brand',
        'categories',
        'variants',
        'reviews',
        ElasticInclude::count('reviews'),
        ElasticInclude::callback('recentReviews', function ($builder) {
            $builder->with(['reviews' => function ($query) {
                $query->latest()->limit(5);
            }]);
        }),
    ])
    ->build()
    ->execute()
    ->models();
```

### Social Network with Users

```php
ElasticQueryWizard::for(User::class)
    ->allowedFilters([
        ElasticFilter::match('name'),
        ElasticFilter::geoDistance('location', 'nearby'),
    ])
    ->allowedIncludes([
        'profile',
        'posts',
        ElasticInclude::count('followers'),
        ElasticInclude::count('following'),
        ElasticInclude::callback('mutualFriends', function ($builder) {
            // Custom logic for mutual friends
        }),
    ])
    ->build()
    ->execute()
    ->models();
```
