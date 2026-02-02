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

use CloudCreativity\JsonApi\Testing\Document;
use PHPUnit\Framework\Constraint\Constraint;

class EmptyOrMissingList extends Constraint
{

    /**
     * @var string
     */
    private string $pointer;

    /**
     * @var bool
     */
    private bool $missing;

    /**
     * ListEmptyOrMissing constructor.
     *
     * @param string $pointer
     * @param bool $missing
     *      whether the list is allowed to be missing from the document.
     */
    public function __construct(string $pointer, bool $missing = true)
    {
        $this->pointer = $pointer;
        $this->missing = $missing;
    }

    /**
     * @param mixed $other
     * @return bool
     */
    public function matches($other): bool
    {
        $document = Document::cast($other);

        if ($this->missing && !$document->has($this->pointer)) {
            return true;
        }

        $actual = $document->get($this->pointer);

        return \is_array($actual) && empty($actual);
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        $message = $this->missing ? 'has a missing or empty list' : 'has an empty list';

        return "{$message} at [{$this->pointer}]";
    }

    /**
     * @inheritdoc
     */
    protected function failureDescription($document): string
    {
        return 'the document ' . $this->toString() . PHP_EOL . PHP_EOL
            . Document::cast($document);
    }

}
