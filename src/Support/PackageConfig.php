<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Support;

use InvalidArgumentException;

/**
 * Reads the `elastic-query-wizard.*` config values. The package ships no
 * config file, so every value has a default.
 *
 * @internal
 */
final class PackageConfig
{
    /**
     * A limit: a positive integer, a string of digits holding one (as `env()`
     * returns), or null for no limit. The default applies when the key is not set.
     *
     * @throws InvalidArgumentException For any other value
     */
    public static function positiveIntOrNull(string $key, ?int $default): ?int
    {
        $value = config('elastic-query-wizard.'.$key, $default);

        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^\d+\z/', $value) === 1) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        throw new InvalidArgumentException(
            "Config `elastic-query-wizard.{$key}` must be a positive integer or null."
        );
    }
}
