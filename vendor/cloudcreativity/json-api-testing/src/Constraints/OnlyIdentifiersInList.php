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
use SebastianBergmann\Comparator\ComparisonFailure;

/**
 * Class OnlyIdentifiersInList
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class OnlyIdentifiersInList extends OnlySubsetsInList
{

    /**
     * @inheritdoc
     */
    protected function fail($other, $description, ?ComparisonFailure $comparisonFailure = null): never
    {
        $comparisonFailure = Compare::failure(
            $this->expected,
            Document::cast($other)->get($this->pointer)
        );

        parent::fail($other, $description, $comparisonFailure);
    }

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return "the list at [{$this->pointer}] only contains the values:" . PHP_EOL
            . $this->toString() . PHP_EOL . PHP_EOL
            . "within JSON API document:" . PHP_EOL
            . Document::cast($document);
    }

    /**
     * @param array $expected
     * @param mixed $actual
     * @return bool
     */
    protected function compare(array $expected, $actual): bool
    {
        if (!Compare::subset($expected, $actual, $this->strict)) {
            return false;
        }

        return Compare::resourceIdentifier($actual);
    }

}
