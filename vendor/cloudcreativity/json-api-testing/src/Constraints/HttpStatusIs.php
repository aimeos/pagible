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

/**
 * Class HttpStatusIs
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class HttpStatusIs extends Constraint
{

    /**
     * @var int
     */
    private int $expected;

    /**
     * @var mixed
     */
    private $content;

    /**
     * HttpStatusIs constructor.
     *
     * @param int $expected
     * @param mixed $content
     */
    public function __construct(int $expected, $content = null)
    {
        $this->expected = $expected;
        $this->content = $content;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return (string) $this->expected;
    }

    /**
     * @inheritdoc
     */
    protected function failureDescription($other): string
    {
        $message = "the HTTP status {$other} is {$this->toString()}";
        $document = Document::create($this->content);

        if ($document && $document->has('errors')) {
            return $message . ". The response errors were:"  . PHP_EOL . $document;
        }

        return $message;
    }

    /**
     * @param mixed $other
     * @return bool
     */
    protected function matches($other): bool
    {
        if (!is_string($other) && !is_int($other)) {
            return false;
        }

        return $this->expected === (int) $other;
    }

}
