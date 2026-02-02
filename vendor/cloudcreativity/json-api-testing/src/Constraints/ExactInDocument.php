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
use CloudCreativity\JsonApi\Testing\Utils\JsonObject;
use JsonSerializable;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Class ExactInDocument
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class ExactInDocument extends Constraint
{

    /**
     * @var array|null
     */
    private ?array $expected;

    /**
     * @var string
     */
    private string $pointer;

    /**
     * @var bool
     */
    private bool $strict;

    /**
     * ExactInDocument constructor.
     *
     * @param JsonSerializable|array|null $expected
     *      the expected value
     * @param string $pointer
     *      the JSON pointer to the object in the JSON API document.
     * @param bool $strict
     */
    public function __construct($expected, string $pointer, bool $strict = true)
    {
        $expected = JsonObject::nullable($expected);
        $this->expected = $expected ? $expected->toArray() : null;
        $this->pointer = $pointer;
        $this->strict = $strict;
    }

    /**
     * @inheritdoc
     */
    public function evaluate($other, string $description = '', bool $returnResult = false): ?bool
    {
        $actual = Document::cast($other)->get($this->pointer);
        $result = Compare::exact($this->expected, $actual, $this->strict);

        if ($returnResult) {
            return $result;
        }

        if (!$result) {
            $this->fail($other, $description, Compare::failure($this->expected, $actual));
        }

        return null;
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
    protected function failureDescription($document): string
    {
        return "the member at [{$this->pointer}] exactly matches:" . PHP_EOL
            . $this->toString() . PHP_EOL . PHP_EOL
            . "within JSON API document:" . PHP_EOL
            . Document::cast($document);
    }

}
