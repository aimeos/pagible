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
 * Class HttpStatusIsSuccessful
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class HttpStatusIsSuccessful extends Constraint
{

    /**
     * @var string|null
     */
    private ?string $content;

    /**
     * HttpStatusIsSuccessful constructor.
     *
     * @param string|null $content
     */
    public function __construct(?string $content = null)
    {
        $this->content = $content;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return 'successful';
    }

    /**
     * @inheritdoc
     */
    protected function failureDescription($other): string
    {
        if (204 === intval($other)) {
            return 'the HTTP status 204 No Content is invalid as there is content.';
        }

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

        $other = (int) $other;

        if (204 === $other && !empty($this->content)) {
            return false;
        }

        return 200 <= $other && 299 >= $other;
    }

}
