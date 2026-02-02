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

/**
 * Class ExactInList
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class ExactInList extends SubsetInList
{

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return "the list at [{$this->pointer}] contains the values:" . PHP_EOL
            . $this->toString() . PHP_EOL . PHP_EOL
            . "within JSON API document:" . PHP_EOL
            . Document::cast($document);
    }

    /**
     * @param $actual
     * @return bool
     */
    protected function compare($actual): bool
    {
        return Compare::exact($this->expected, $actual, $this->strict);
    }

}
