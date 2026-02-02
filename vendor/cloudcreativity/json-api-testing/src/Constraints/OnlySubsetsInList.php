<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\JsonApi\Testing\Constraints;

use CloudCreativity\JsonApi\Testing\Compare;
use CloudCreativity\JsonApi\Testing\Document;
use CloudCreativity\JsonApi\Testing\Utils\JsonStack;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Constraint\Constraint;
use SebastianBergmann\Comparator\ComparisonFailure;

/**
 * Class OnlySubsetsInList
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class OnlySubsetsInList extends Constraint
{

    /**
     * @var array
     */
    protected array $expected;

    /**
     * @var string
     */
    protected string $pointer;

    /**
     * @var bool
     */
    protected bool $strict;

    /**
     * OnlySubsetsInList constructor.
     *
     * @param iterable $expected
     *      the expected object
     * @param string $pointer
     *      the JSON pointer to the array in the JSON API document.
     * @param bool $strict
     */
    public function __construct(iterable $expected, string $pointer, bool $strict = true)
    {
        $this->expected = JsonStack::cast($expected)->toArray();
        $this->pointer = $pointer;
        $this->strict = $strict;
    }

    /**
     * @inheritdoc
     */
    public function matches($other): bool
    {
        $other = Document::cast($other)->get($this->pointer);

        if (!is_array($other)) {
            return false;
        }

        $allValid = Collection::make($other)->every(function ($item) {
            return $this->expected((array) $item);
        });

        if (!$allValid) {
            return false;
        }

        return Collection::make($this->expected)->every(function ($expected) use ($other) {
            return $this->exists($expected, $other);
        });
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return Compare::stringify($this->expected);
    }

    /**
     * @inheritdoc
     */
    protected function fail($other, $description, ?ComparisonFailure $comparisonFailure = null): never
    {
        if (!$comparisonFailure) {
            $comparisonFailure = Compare::failure(
                $this->expected,
                Document::cast($other)->get($this->pointer),
                true
            );
        }

        parent::fail($other, $description, $comparisonFailure);
    }

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return "the array at [{$this->pointer}] only contains the subsets:" . PHP_EOL
            . $this->toString() . PHP_EOL . PHP_EOL
            . "within JSON API document:" . PHP_EOL
            . Document::cast($document);
    }

    /**
     * Is the actual subset expected?
     *
     * @param array $actual
     * @return bool
     */
    protected function expected(array $actual): bool
    {
        return Collection::make($this->expected)->contains(function ($expected) use ($actual) {
            return $this->compare($expected, $actual);
        });
    }

    /**
     * Does the expected subset exist in the actual array?
     *
     * @param array $expected
     * @param mixed $actual
     * @return bool
     */
    protected function exists(array $expected, $actual): bool
    {
        if (!is_array($actual)) {
            return false;
        }

        return Collection::make($actual)->contains(function ($item) use ($expected) {
            return $this->compare($expected, $item);
        });
    }

    /**
     * @param array $expected
     * @param mixed $actual
     * @return bool
     */
    protected function compare(array $expected, $actual): bool
    {
        return Compare::subset($expected, $actual, $this->strict);
    }

}
