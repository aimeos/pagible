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
 * Class IdentifierInDocument
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class IdentifierInDocument extends SubsetInDocument
{

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return "the member at [{$this->pointer}] matches the resource identifier:" . PHP_EOL
            . $this->toString() . PHP_EOL . PHP_EOL
            . "within JSON API document:" . PHP_EOL
            . Document::cast($document);
    }

    /**
     * @param array $expected
     * @param $actual
     * @param bool $strict
     * @return bool
     */
    protected function compare(array $expected, $actual, bool $strict): bool
    {
        if (!parent::compare($expected, $actual, $strict)) {
            return false;
        }

        return Compare::resourceIdentifier($actual);
    }

    /**
     * @param $actual
     * @return ComparisonFailure
     */
    protected function failure($actual): ComparisonFailure
    {
        return Compare::failure($this->expected, $actual, false);
    }
}
