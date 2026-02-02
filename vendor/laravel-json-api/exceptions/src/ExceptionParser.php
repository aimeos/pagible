<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Exceptions;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Core\Exceptions\JsonApiException;
use LaravelJsonApi\Core\Responses\ErrorResponse;
use Throwable;

final class ExceptionParser
{

    /**
     * @var Pipeline
     */
    private Pipeline $pipeline;

    /**
     * @var Error|null
     */
    private ?Error $default = null;

    /**
     * @var bool
     */
    private bool $alwaysRender = false;

    /**
     * @var Closure[]
     */
    private array $accept = [];

    /**
     * @var array
     */
    private array $pipes = [
        Pipes\AuthenticationExceptionHandler::class,
        Pipes\HttpExceptionHandler::class,
        Pipes\RequestExceptionHandler::class,
        Pipes\ValidationExceptionHandler::class,
    ];

    /**
     * Get an exception renderer closure.
     *
     * @return Closure
     */
    public static function renderer(): Closure
    {
        return self::make()->renderable();
    }

    /**
     * Fluent constructor.
     *
     * @return static
     */
    public static function make(): self
    {
        return new self(new Pipeline(Container::getInstance()));
    }

    /**
     * ExceptionParser constructor.
     *
     * @param Pipeline $pipeline
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Use the provided pipes to parse exceptions.
     *
     * @param array|null $pipes
     * @return $this
     */
    public function using(?array $pipes): self
    {
        if (is_array($pipes)) {
            $this->pipes = $pipes;
        }

        return $this;
    }

    /**
     * Add pipes before the existing pipes.
     *
     * @param array $pipes
     * @return $this
     */
    public function prepend(array $pipes): self
    {
        $this->pipes = array_merge($pipes, $this->pipes);

        return $this;
    }

    /**
     * Add pipes to the end of the existing pipes.
     *
     * @param array $pipes
     * @return $this
     */
    public function append(array $pipes): self
    {
        $this->pipes = array_merge($this->pipes, $pipes);

        return $this;
    }

    /**
     * Use the provided error as the default error.
     *
     * @param Error|Enumerable|array $error
     * @return $this
     */
    public function withDefault($error): self
    {
        $this->default = Error::cast($error);

        return $this;
    }

    /**
     * Render the exception, if the request wants the JSON API media type.
     *
     * @param Throwable $ex
     * @param Request|mixed $request
     * @return Response|mixed|null
     */
    public function render(Throwable $ex, $request)
    {
        if ($this->isRenderable($ex, $request)) {
            return $this
                ->parse($ex, $request)
                ->toResponse($request);
        }

        return null;
    }

    /**
     * Does the HTTP request require a JSON API error response?
     *
     * This method determines if we need to render a JSON API error response
     * for the client. We need to do this if the client has requested JSON
     * API via its Accept header.
     *
     * @param Throwable $e
     * @param Request|mixed $request
     * @return bool
     */
    public function isRenderable(Throwable $e, $request): bool
    {
        if ($this->alwaysRender || $this->mustAccept($e, $request)) {
            return true;
        }

        if ($e instanceof JsonApiException) {
            return true;
        }

        $acceptable = $request->getAcceptableContentTypes();

        return isset($acceptable[0]) && 'application/vnd.api+json' === $acceptable[0];
    }

    /**
     * Parse the exception to an error response.
     *
     * @param Throwable $ex
     * @param Request|mixed $request
     * @return ErrorResponse
     */
    public function parse(Throwable $ex, $request): ErrorResponse
    {
        if ($ex instanceof JsonApiException) {
            return $ex->prepareResponse($request);
        }

        return $this->pipeline
            ->send($ex)
            ->through($this->pipes)
            ->via('handle')
            ->then(fn(Throwable $ex) => new ErrorResponse($this->getDefaultError($ex)));
    }

    /**
     * Always render JSON:API exceptions.
     *
     * @return $this
     */
    public function acceptsAll(): self
    {
        $this->alwaysRender = true;

        return $this;
    }

    /**
     * Always render JSON:API errors if the client accepts JSON.
     *
     * @return $this
     */
    public function acceptsJson(): self
    {
        $this->accept[] = static fn($ex, $request): bool => $request->wantsJson();

        return $this;
    }

    /**
     * Always render JSON:API errors if the current route has *any* of the provided middleware.
     *
     * @param mixed ...$middleware
     * @return $this
     */
    public function acceptsMiddleware(...$middleware): self
    {
        $this->accept[] = static function ($ex, $request) use ($middleware): bool {
            $route = $request->route();

            return Collection::make($middleware)
                ->intersect($route ? $route->gatherMiddleware() : [])
                ->isNotEmpty();
        };

        return $this;
    }

    /**
     * Use the provided closure to determine if JSON:API errors should be rendered.
     *
     * @param Closure $callback
     * @return $this
     */
    public function accept(Closure $callback): self
    {
        $this->accept[] = $callback;

        return $this;
    }

    /**
     * @return Closure
     */
    public function renderable(): Closure
    {
        return fn(Throwable $ex, $request) => $this->render($ex, $request);
    }

    /**
     * @param Throwable $ex
     * @param $request
     * @return bool
     */
    private function mustAccept(Throwable $ex, $request): bool
    {
        foreach ($this->accept as $fn) {
            if (true === $fn($ex, $request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the default JSON API error.
     *
     * @param Throwable $ex
     * @return Error|ErrorList
     */
    private function getDefaultError(Throwable $ex): Error|ErrorList
    {
        if ($this->default) {
            return $this->default;
        }

        $errors = [];
        $debug = config('app.debug');

        do {
            $errors[] = $this->convertExceptionToError($ex, $debug);
        } while ($ex = $ex->getPrevious());

        return new ErrorList(...$errors);
    }

    /**
     * @param Throwable $ex
     * @param bool $debug
     * @return Error
     */
    private function convertExceptionToError(Throwable $ex, bool $debug): Error
    {
        $error = Error::make()
            ->setStatus(500)
            ->setTitle(__(Response::$statusTexts[500]));

        if ($debug) {
            $error
                ->setCode($ex->getCode())
                ->setDetail($ex->getMessage())
                ->setMeta($this->convertExceptionToMeta($ex));
        }

        return $error;
    }

    /**
     * Convert the provided exception to error meta.
     *
     * In this method we mirror the information that Laravel's exception handler
     * puts into its JSON representation of an exception when in debug mode.
     *
     * @param Throwable $ex
     * @return array<string, mixed>
     * @see \Illuminate\Foundation\Exceptions\Handler::convertExceptionToArray()
     */
    private function convertExceptionToMeta(Throwable $ex): array
    {
        return [
            'exception' => $ex::class,
            'file' => $ex->getFile(),
            'line' => $ex->getLine(),
            'trace' => Collection::make($ex->getTrace())
                ->map(fn($trace) => Arr::except($trace, ['args']))
                ->all(),
        ];
    }

}
