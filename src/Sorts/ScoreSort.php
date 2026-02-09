<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Sorts\AbstractSort;

class ScoreSort extends AbstractSort
{
    public static function make(?string $alias = null): static
    {
        return new static('_score', $alias);
    }

    public function getType(): string
    {
        return 'score';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        $subject->sort(Sort::score()->order($direction));

        return $subject;
    }
}
