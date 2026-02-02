<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

use LaravelJsonApi\Contracts\ErrorProvider;
use LaravelJsonApi\Core\Document\ErrorList;

class Document implements ErrorProvider
{

    /**
     * @var object
     */
    private object $document;

    /**
     * @var ErrorList
     */
    private ErrorList $errors;

    /**
     * BaseDocument constructor.
     *
     * @param object $document
     */
    public function __construct(object $document)
    {
        $this->document = $document;
        $this->errors = new ErrorList();
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->document->{$name});
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->document->{$name};
    }

    /**
     * Get a value from the document using dot notation.
     *
     * @param string $path
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $path, $default = null)
    {
        return data_get($this->document, $path, $default);
    }

    /**
     * @return ErrorList
     */
    public function errors(): ErrorList
    {
        return $this->toErrors();
    }

    /**
     * @inheritDoc
     */
    public function toErrors(): ErrorList
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return $this->errors->isEmpty();
    }

    /**
     * @return bool
     */
    public function invalid(): bool
    {
        return !$this->valid();
    }

    /**
     * @return object
     */
    public function toBase(): object
    {
        return clone $this->document;
    }

}
