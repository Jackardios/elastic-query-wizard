# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [3.0.0] - Unreleased

Version 3 is a rewrite on top of `laravel-query-wizard` v3 and `es-scout-driver`: fluent `allowed*()` configuration,
`ElasticFilter`/`ElasticSort`/`ElasticInclude` factories, resource schemas, filter groups and bool clause methods. See
[UPGRADE.md](UPGRADE.md) for migrating from v2.x and from `dev-master` snapshots. The entries below list the changes
made since those snapshots.

### Requirements

- PHP 8.2+, Laravel 12.61.1+ or 13.12.0+, `laravel-query-wizard` ^3.0.0-rc.3 and `es-scout-driver` ^1.0.0-rc.1.
  CI runs Elasticsearch 8.19 and 9.5.

### Changed

- The searched models are loaded through `laravel-query-wizard`'s `EloquentShape`, so includes, sparse fieldsets and
  appends behave as in `EloquentQueryWizard`: an explicit empty root fieldset hides every root attribute, the primary
  and Scout keys are selected and hidden unless requested, and a relation fieldset runs after the eager-load
  constraint registered in `modifyQuery()`.
- The soft delete mode lives on the search builder (`$wizard->withTrashed()`), as in `es-scout-driver` 1.0.
- A value is split by the filter separator once; the pattern and text filters (`prefix`, `wildcard`, `regexp`,
  `fuzzy`, the match family, `queryString`, `simpleQueryString`, `moreLikeThis`) keep it whole.
- Unreadable filter values are 400s: `InvalidRangeValue`, `InvalidGeoBoundingBoxValue`, `InvalidGeoDistanceValue` and
  `InvalidGeoShapeValue` extend `laravel-query-wizard`'s `InvalidFilterValue` (was 422) and name the filter by its
  public name; their factories take the value and the filter. Range bounds must be decimal numbers or ISO 8601 dates,
  geo distances positive numbers with a unit, coordinates in range; exists and null filters take booleans and the
  trashed filter `with`, `only`, `without`, `true` or `false`.
- `queryString` and `simpleQueryString` search their property unless `fields` is set, and `queryString` refuses
  terms that start with a wildcard unless `allow_leading_wildcard` is set.
- A more-like-this document reference takes only an `_id` and is read from the searched index; a geo shape
  `indexed_shape` needs `indexedShapes($index, $path)` and takes only an `id`.
- `FilterValueSanitizer` is `@internal`; custom filters read values with `laravel-query-wizard`'s `FilterValueParser`.
- `withParameters()` checks each name against the filter's query when the filter is configured
  (`InvalidArgumentException`).
- Date range bounds are read like `laravel-query-wizard` reads dates: a date or an ISO 8601 date-time (other values,
  epoch numbers and date math included, are 400s), in the application timezone or the filter's `timezone()`, with a
  date `to` covering the whole day. The bounds are sent as ISO 8601 date-times with an offset and
  `format: strict_date_optional_time`; `dateFormat()` is deprecated in favor of `esFormat()`, whose format is sent
  followed by `||strict_date_optional_time`.
- The text and pattern filters take one value: a list is a 400 for `prefix`, `wildcard`, `regexp`, `fuzzy`, the match
  family, `queryString` and `simpleQueryString` (the match family and the query string filters joined it with `,`).
  `moreLikeThis` reads a list as several texts.
- `default()`, `prepareValueWith()`, `when()` and `asBoolean()` on a filter group throw a `LogicException`.

### Added

- `GeoShapeFilter::indexedShapes()`.
- `DateRangeFilter::esFormat()`.
- `withParameters(['boost' => …])` on prefix and exists filters (their `es-scout-driver` queries gained `boost()`).
- `maxLength()` on the text and pattern filters; a longer value is a 400. `regexp` defaults to 1000 characters,
  Elasticsearch's `index.max_regex_length`.
- Geo bounding boxes take named edges (`left`, `bottom`, `right`, `top`); geo shape polygons keep their holes and are
  closed when their last point differs from their first.

### Fixed

- Negated exists and null filters stay in the filter's clause, so they work in `inShould()`, `inMustNot()` and bool
  groups.
- Random sorts wrap the whole query in a `function_score` and sort by the random score alone.
- A bool group leaves out `minimum_should_match` when the request fills none of its should children.
- A group child that cannot run in a group, a group named like another filter and a filter in two groups throw when
  the wizard is configured or built instead of misbehaving on a request.
- Two nested groups with `innerHits()` on one path no longer fail the search: an inner hits result set without a
  `name` is named after its group.
- Geo shape points are checked like bounding box and distance coordinates: two numbers, in range, and an envelope
  whose top is not below its bottom; other values are 400s instead of 500s from Elasticsearch.
- A range bound left empty is no bound; coordinates that overflow to infinity no longer fail the JSON encoding.
- A clone of a wizard whose built search was changed keeps the change lock.

### Security

- `disallowedFilters()` removes a filter inside a bool or nested group, as it removes one at the root: its request key
  is refused and its default is not applied. It used to reach Elasticsearch.
- A client can no longer read documents of other indices through more-like-this references or indexed shapes, or
  search fields outside a query string filter's property without a field-qualified term.
