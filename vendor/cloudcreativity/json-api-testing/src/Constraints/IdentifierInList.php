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
 * Class IdentifierInList
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class IdentifierInList extends SubsetInList
{

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return "the array at [{$this->pointer}] contains the resource identifier:" . PHP_EOL
            . $this->toString() . PHP_EOL . PHP_EOL
            . "within JSON API document:" . PHP_EOL
            . Document::cast($document);
    }

    /**
     * @inheritdoc
     */
    protected function compare($actual): bool
    {
        if (!parent::compare($actual)) {
            return false;
        }

        return Compare::resourceIdentifier($actual);
    }
}
