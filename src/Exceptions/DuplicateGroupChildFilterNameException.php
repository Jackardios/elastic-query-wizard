<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

/**
 * Thrown when a group children tree contains duplicate leaf filter names.
 *
 * Group values are resolved by leaf filter names only, therefore duplicate
 * leaf names would make value routing ambiguous.
 */
class DuplicateGroupChildFilterNameException extends \InvalidArgumentException
{
    /**
     * @param array<int, string> $duplicates
     */
    public function __construct(string $groupName, array $duplicates)
    {
        $names = implode(', ', $duplicates);

        parent::__construct(
            "Group '{$groupName}' contains duplicate leaf filter names: {$names}. "
            . 'Each filter alias inside a group tree must be unique.'
        );
    }

    /**
     * @param array<int, string> $duplicates
     */
    public static function forGroup(string $groupName, array $duplicates): self
    {
        return new self($groupName, $duplicates);
    }
}
