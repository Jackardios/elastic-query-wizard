<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\QueryWizard\Sorts\AbstractSort;

class FieldSort extends AbstractSort
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'field';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        $subject->sort($this->property, $direction);

        return $subject;
    }
}
