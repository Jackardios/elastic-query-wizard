<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\LimitsValueLength;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * A filter whose value is one text or pattern, which may contain the
 * separator; a list is a 400.
 *
 * @internal
 */
abstract class AbstractTextFilter extends AbstractElasticFilter
{
    use HasParameters;
    use LimitsValueLength;

    protected function __construct(string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->withoutValueSplitting();
    }

    /**
     * The query for a text that is not blank and not longer than maxLength().
     *
     * @throws InvalidFilterValue
     */
    abstract protected function buildTextQuery(string $text): QueryInterface;

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $text = FilterValueSanitizer::text($value, $this);

        if ($text === null) {
            return null;
        }

        $this->assertValueLength($text);

        return $this->applyParametersOnQuery($this->buildTextQuery($text));
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
