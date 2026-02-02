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
use Illuminate\Support\Collection;

/**
 * Class IdentifiersInOrder
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class IdentifiersInOrder extends SubsetsInOrder
{

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return "the array at [{$this->pointer}] contains the resource identifiers in order:" . PHP_EOL
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

        return Collection::make($actual)->every(function (array $identifier) {
            return Compare::resourceIdentifier($identifier);
        });
    }
}
