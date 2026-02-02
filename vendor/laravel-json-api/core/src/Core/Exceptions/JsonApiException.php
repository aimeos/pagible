<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Enumerable;
use LaravelJsonApi\Contracts\ErrorProvider;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Core\Responses\Concerns\IsResponsable;
use LaravelJsonApi\Core\Responses\ErrorResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class JsonApiException extends Exception implements HttpExceptionInterface, Responsable, ErrorProvider
{
    use IsResponsable;

    /**
     * @var ErrorList
     */
    private ErrorList $errors;

    /**
     * Fluent constructor.
     *
     * @param ErrorList|ErrorProvider|Error $errors
     * @param Throwable|null $previous
     * @return static
     */
    public static function make($errors, ?Throwable $previous = null): self
    {
        return new self($errors, $previous);
    }

    /**
     * Construct an exception for a single JSON:API error.
     *
     * @param Error|Enumerable|array $error
     * @param Throwable|null $previous
     * @return static
     */
    public static function error($error, ?Throwable $previous = null): self
    {
        return new self(Error::cast($error), $previous);
    }

    /**
     * JsonApiException constructor.
     *
     * @param ErrorList|ErrorProvider|Error|Error[] $errors
     * @param Throwable|null $previous
     * @param array $headers
     */
    public function __construct($errors, ?Throwable $previous = null, array $headers = [])
    {
        parent::__construct('JSON:API error', 0, $previous);
        $this->errors = ErrorList::cast($errors);
        $this->withHeaders($headers);
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->errors->status();
    }

    /**
     * Does the exception have a 4xx status code?
     *
     * @return bool
     */
    public function is4xx(): bool
    {
        $status = $this->getStatusCode();

        return (400 <= $status) && (500 > $status);
    }

    /**
     * Does the exception have a 5xx status code?
     *
     * @return bool
     */
    public function is5xx(): bool
    {
        $status = $this->getStatusCode();

        return (500 <= $status) && (600 > $status);
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return ErrorList
     */
    public function getErrors(): ErrorList
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
     * Get the exception's context information.
     *
     * @return array
     */
    public function context(): array
    {
        return [
            'status' => $this->getStatusCode(),
            'errors' => $this->errors->toArray(),
        ];
    }

    /**
     * @param $request
     * @return ErrorResponse
     */
    public function prepareResponse($request): ErrorResponse
    {
        return $this->errors
            ->prepareResponse($request)
            ->withJsonApi($this->jsonApi())
            ->withMeta($this->meta())
            ->withLinks($this->links())
            ->withHeaders($this->headers);
    }

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this
            ->prepareResponse($request)
            ->toResponse($request);
    }
}
